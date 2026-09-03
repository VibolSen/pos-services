<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    protected function getTenantId(Request $request): ?string
    {
        return $request->header('X-Tenant-Id')
            ?? $request->user()?->tenant_id
            ?? $request->query('tenant_id')
            ?? null;
    }

    public function purchases(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = DB::table('purchases');

        if (!empty($tenantId) && $tenantId !== 'all') {
            if (Schema::hasColumn('purchases', 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }
        }

        $purchases = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $purchases]);
    }

    public function purchaseOrders(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = DB::table('purchase_orders');

        if (!empty($tenantId) && $tenantId !== 'all') {
            $hasTenant = DB::table('purchase_orders')->where('tenant_id', $tenantId)->exists();
            if ($hasTenant) {
                $query->where('tenant_id', $tenantId);
            } else {
                $query->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                });
            }
        }

        $orders = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $orders]);
    }
}
