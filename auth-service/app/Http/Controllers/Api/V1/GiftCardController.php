<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GiftCardController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureSeeded();
        $cards = DB::table('gift_cards')->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $cards]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'card_code' => 'nullable|string',
            'customer' => 'nullable|string',
            'balance' => 'required|numeric|min:0.01',
        ]);

        $code = $validated['card_code'] ?? ('GC-' . rand(1000, 9999) . '-' . rand(1000, 9999));

        $id = (string) Str::uuid();
        DB::table('gift_cards')->insert([
            'id' => $id,
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

    protected function ensureSeeded()
    {
        if (DB::table('gift_cards')->count() === 0) {
            DB::table('gift_cards')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'card_code' => 'GC-9920-1120',
                    'customer' => 'John Doe',
                    'balance' => 50.00,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'card_code' => 'GC-8830-4491',
                    'customer' => 'Sophea Kim',
                    'balance' => 100.00,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}
