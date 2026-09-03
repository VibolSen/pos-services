<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GiftCardController extends Controller
{
    protected function getTenantId(Request $request): ?string
    {
        return $request->header('X-Tenant-Id')
            ?? $request->user()?->tenant_id
            ?? $request->query('tenant_id')
            ?? null;
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = DB::table('gift_cards');

        if (!empty($tenantId) && $tenantId !== 'all') {
            $query->where('tenant_id', $tenantId);
        }

        $cards = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $cards]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $validated = $request->validate([
            'card_code' => 'nullable|string',
            'customer' => 'nullable|string',
            'balance' => 'required|numeric|min:0.01',
        ]);

        $code = $validated['card_code'] ?? ('GC-' . rand(1000, 9999) . '-' . rand(1000, 9999));
        $id = (string) Str::uuid();

        DB::table('gift_cards')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'card_code' => $code,
            'customer' => $validated['customer'] ?? 'Walk-in Customer',
            'balance' => $validated['balance'],
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Gift card issued successfully.',
            'data' => DB::table('gift_cards')->where('id', $id)->first(),
        ], 201);
    }
}
