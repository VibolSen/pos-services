<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class CheckoutService
{
    public function finalizeSale(array $data, ?User $cashier = null): array
    {
        return DB::transaction(function () use ($data, $cashier) {
            // 1. Idempotency Check
            $existingSale = DB::table('sales')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existingSale) {
                $lines = DB::table('sale_lines')->where('sale_id', $existingSale->id)->get();
                return [
                    'sale' => $existingSale,
                    'lines' => $lines,
                    'is_duplicate' => true,
                ];
            }

            // 2. Server-side totals calculation
            $subtotal = 0;
            $saleLines = [];

            foreach ($data['items'] as $item) {
                $product = DB::table('products')->where('id', $item['product_id'])->where('is_active', true)->first();
                if (!$product) {
                    // Check catalog_db.products for catalog-created products
                    try {
                        $catalogProduct = DB::table('catalog_db.products')->where('id', $item['product_id'])->first();
                        if ($catalogProduct) {
                            DB::table('products')->updateOrInsert(
                                ['id' => $catalogProduct->id],
                                [
                                    'name' => $catalogProduct->name,
                                    'sku' => $catalogProduct->sku ?? ('SKU-' . substr($catalogProduct->id, 0, 8)),
                                    'barcode' => $catalogProduct->barcode ?? null,
                                    'category_id' => $catalogProduct->category_id ?? null,
                                    'brand_id' => $catalogProduct->brand_id ?? null,
                                    'selling_price' => $catalogProduct->selling_price ?? 0,
                                    'cost_price' => $catalogProduct->cost_price ?? 0,
                                    'is_active' => $catalogProduct->is_active ?? true,
                                    'min_reorder_point' => $catalogProduct->min_reorder_point ?? 5,
                                    'created_at' => $catalogProduct->created_at ?? now(),
                                    'updated_at' => now(),
                                ]
                            );
                            $product = DB::table('products')->where('id', $item['product_id'])->first();
                        }
                    } catch (\Exception $syncErr) {
                        // proceed to next fallback
                    }
                }

                // If still not found, create a fallback product stub using payload details
                if (!$product) {
                    $fallbackName = $item['name'] ?? ('Product ' . substr($item['product_id'], 0, 8));
                    $fallbackPrice = $item['price'] ?? 0;
                    DB::table('products')->updateOrInsert(
                        ['id' => $item['product_id']],
                        [
                            'name' => $fallbackName,
                            'sku' => 'SKU-' . substr($item['product_id'], 0, 8),
                            'selling_price' => $fallbackPrice,
                            'cost_price' => 0,
                            'is_active' => true,
                            'min_reorder_point' => 5,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                    $product = DB::table('products')->where('id', $item['product_id'])->first();
                }

                if (!$product) {
                    throw new Exception("Product ID {$item['product_id']} not found or inactive.");
                }

                $lineSubtotal = $product->selling_price * $item['quantity'];
                $subtotal += $lineSubtotal;

                $saleLines[] = [
                    'product_id' => $product->id,
                    'variant_id' => $item['variant_id'] ?? null,
                    'product_name' => $product->name,
                    'unit_price' => $product->selling_price,
                    'quantity' => $item['quantity'],
                    'discount_amount' => 0,
                    'subtotal' => $lineSubtotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $discountTotal = $data['discount_total'] ?? 0;
            $taxTotal = $data['tax_total'] ?? 0;
            $grandTotal = ($subtotal - $discountTotal) + $taxTotal;

            // 3. Create Immutable Sale Header
            $outlet = DB::table('outlets')->where('id', $data['outlet_id'])->first();
            $outletCode = $outlet ? strtoupper($outlet->code) : 'MAIN';

            $todayCount = DB::table('sales')
                ->where('outlet_id', $data['outlet_id'])
                ->whereDate('created_at', now()->toDateString())
                ->count();

            $seqStr = str_pad($todayCount + 1, 5, '0', STR_PAD_LEFT);
            $receiptNumber = "REC-{$outletCode}-" . date('Ymd') . "-{$seqStr}";

            $saleId = (string) \Illuminate\Support\Str::uuid();

            $cashierId = $cashier?->id ?? DB::table('users')->value('id') ?? $saleId;

            DB::table('sales')->insert([
                'id' => $saleId,
                'outlet_id' => $data['outlet_id'],
                'register_id' => $data['register_id'],
                'shift_id' => $data['shift_id'] ?? null,
                'user_id' => $cashierId,
                'receipt_number' => $receiptNumber,
                'idempotency_key' => $data['idempotency_key'],
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
                'currency' => $data['currency'] ?? 'USD',
                'status' => 'completed',
                'print_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 4. Attach Sale Lines & Append Inventory Ledger
            foreach ($saleLines as &$line) {
                $line['id'] = (string) \Illuminate\Support\Str::uuid();
                $line['sale_id'] = $saleId;
                DB::table('sale_lines')->insert($line);

                $targetOutletId = (string) $data['outlet_id'];
                $targetProductId = (string) $line['product_id'];

                // Deduct stock from balance if record exists
                try {
                    $balance = DB::table('inventory_balances')
                        ->where('outlet_id', $targetOutletId)
                        ->where('product_id', $targetProductId)
                        ->first();

                    if ($balance) {
                        DB::table('inventory_balances')
                            ->where('id', $balance->id)
                            ->decrement('on_hand', $line['quantity']);
                    }
                } catch (\Exception $balanceErr) {
                    // Non-fatal if balance row doesn't exist
                }

                // Record append-only movement
                try {
                    DB::table('inventory_movements')->insert([
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'outlet_id' => $targetOutletId,
                        'product_id' => $targetProductId,
                        'variant_id' => $line['variant_id'],
                        'quantity_change' => -$line['quantity'],
                        'movement_type' => 'sale',
                        'reference_type' => 'Sale',
                        'reference_id' => $saleId,
                        'created_by' => $cashierId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Exception $moveErr) {
                    // Non-fatal
                }
            }

            // 5. Record Payment
            $paymentId = (string) \Illuminate\Support\Str::uuid();
            DB::table('payments')->insert([
                'id' => $paymentId,
                'sale_id' => $saleId,
                'tender_type' => $data['tender_type'] ?? 'cash',
                'amount' => $grandTotal,
                'currency' => $data['currency'] ?? 'USD',
                'status' => 'paid',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sale = DB::table('sales')->where('id', $saleId)->first();

            return [
                'sale' => $sale,
                'lines' => $saleLines,
                'is_duplicate' => false,
            ];
        });
    }
}
