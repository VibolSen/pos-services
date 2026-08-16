<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    public function purchases(Request $request)
    {
        $this->ensurePurchasesSeeded();
        $purchases = DB::table('purchases')->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $purchases]);
    }

    public function purchaseOrders(Request $request)
    {
        $this->ensurePoSeeded();
        $orders = DB::table('purchase_orders')->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $orders]);
    }

    protected function ensurePurchasesSeeded()
    {
        if (DB::table('purchases')->count() === 0) {
            DB::table('purchases')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'purchase_ref' => 'PUR-001',
                    'supplier' => 'Angkor Beverage Supply Co.',
                    'invoice_no' => 'INV-2026-9901',
                    'total_amount' => 1450.00,
                    'status' => 'received',
                    'received_date' => '2026-08-10',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'purchase_ref' => 'PUR-002',
                    'supplier' => 'Phnom Penh Wholesale Dairy',
                    'invoice_no' => 'INV-2026-9942',
                    'total_amount' => 820.00,
                    'status' => 'received',
                    'received_date' => '2026-08-09',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    protected function ensurePoSeeded()
    {
        if (DB::table('purchase_orders')->count() === 0) {
            DB::table('purchase_orders')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'po_number' => 'PO-2026-001',
                    'supplier' => 'Angkor Beverage Supply Co.',
                    'items_count' => 12,
                    'est_total' => 2400.00,
                    'status' => 'approved',
                    'order_date' => '2026-08-08',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'po_number' => 'PO-2026-002',
                    'supplier' => 'Khmer Coffee Beans Importers',
                    'items_count' => 5,
                    'est_total' => 950.00,
                    'status' => 'pending',
                    'order_date' => '2026-08-11',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}
