<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class OfflineSyncController extends Controller
{
    /**
     * Batch Sync Offline POS Transactions (Idempotent Execution)
     */
    public function sync(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'transactions' => 'required|array|min:1',
            'transactions.*.offline_id' => 'required|string',
            'transactions.*.receipt_number' => 'required|string',
            'transactions.*.items' => 'required|array|min:1',
            'transactions.*.tenders' => 'required|array|min:1',
            'transactions.*.created_at' => 'nullable|string',
        ]);

        $results = [];
        $syncedCount = 0;
        $alreadySyncedCount = 0;

        foreach ($validated['transactions'] as $tx) {
            $offlineId = $tx['offline_id'];

            // Idempotency check: has this offline_id already been processed?
            $existingSale = DB::table('sales')->where('offline_id', $offlineId)->first();
            if ($existingSale) {
                $alreadySyncedCount++;
                $results[] = [
                    'offline_id' => $offlineId,
                    'status' => 'already_synced',
                    'sale_id' => $existingSale->id,
                    'receipt_number' => $existingSale->receipt_number,
                ];
                continue;
            }

            // Process new offline transaction
            DB::transaction(function () use ($tx, $user, $offlineId, &$syncedCount, &$results) {
                $saleId = (string) Str::uuid();
                $outletId = $user->outlet_id ?? 'outlet-01';
                $registerId = $user->register_id ?? 'register-01';

                $subtotal = 0;
                $linesToInsert = [];

                foreach ($tx['items'] as $item) {
                    $qty = (float) $item['quantity'];
                    $unitPrice = (float) $item['price'];
                    $lineSubtotal = $qty * $unitPrice;
                    $subtotal += $lineSubtotal;

                    $linesToInsert[] = [
                        'id' => (string) Str::uuid(),
                        'sale_id' => $saleId,
                        'product_id' => $item['id'],
                        'product_name' => $item['name'],
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'subtotal' => $lineSubtotal,
                        'created_at' => $tx['created_at'] ?? now(),
                        'updated_at' => now(),
                    ];

                    // Decrement Inventory Balances
                    DB::table('inventory_balances')
                        ->where('outlet_id', $outletId)
                        ->where('product_id', $item['id'])
                        ->decrement('on_hand', $qty);

                    // Append Inventory Movement Ledger
                    DB::table('inventory_movements')->insert([
                        'id' => (string) Str::uuid(),
                        'outlet_id' => $outletId,
                        'product_id' => $item['id'],
                        'quantity_change' => -$qty,
                        'movement_type' => 'sale',
                        'reference_type' => 'Sale',
                        'reference_id' => $saleId,
                        'created_by' => $user->id,
                        'created_at' => $tx['created_at'] ?? now(),
                        'updated_at' => now(),
                    ]);
                }

                $taxAmount = round($subtotal * 0.10, 2);
                $grandTotal = $subtotal + $taxAmount;

                // Insert Sale Record
                DB::table('sales')->insert([
                    'id' => $saleId,
                    'offline_id' => $offlineId,
                    'receipt_number' => $tx['receipt_number'],
                    'outlet_id' => $outletId,
                    'register_id' => $registerId,
                    'user_id' => $user->id,
                    'subtotal' => $subtotal,
                    'tax_total' => $taxAmount,
                    'grand_total' => $grandTotal,
                    'status' => 'completed',
                    'created_at' => $tx['created_at'] ?? now(),
                    'updated_at' => now(),
                ]);

                foreach ($linesToInsert as $l) {
                    DB::table('sale_lines')->insert($l);
                }

                // Insert Payment Tenders
                foreach ($tx['tenders'] as $t) {
                    DB::table('payments')->insert([
                        'id' => (string) Str::uuid(),
                        'sale_id' => $saleId,
                        'tender_type' => $t['tender_type'] ?? 'cash',
                        'amount' => (float) ($t['amount'] ?? $grandTotal),
                        'currency' => 'USD',
                        'status' => 'paid',
                        'reference_number' => 'SYNC-' . strtoupper(substr(uniqid(), -6)),
                        'created_at' => $tx['created_at'] ?? now(),
                        'updated_at' => now(),
                    ]);
                }

                $syncedCount++;
                $results[] = [
                    'offline_id' => $offlineId,
                    'status' => 'synced',
                    'sale_id' => $saleId,
                    'receipt_number' => $tx['receipt_number'],
                ];
            });
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Batch offline transactions sync complete.',
            'summary' => [
                'total_received' => count($validated['transactions']),
                'synced_count' => $syncedCount,
                'already_synced_count' => $alreadySyncedCount,
            ],
            'results' => $results,
        ]);
    }
}
