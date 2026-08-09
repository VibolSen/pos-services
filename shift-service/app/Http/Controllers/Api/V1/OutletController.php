<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('q');

        $query = DB::table('outlets as o');

        if (!empty($search)) {
            $query->where('o.name', 'like', "%{$search}%")
                  ->orWhere('o.code', 'like', "%{$search}%");
        }

        $outlets = $query->select(
                'o.id',
                'o.name',
                'o.code',
                'o.address',
                'o.phone',
                'o.receipt_header',
                'o.receipt_footer',
                'o.is_active',
                'o.created_at',
                DB::raw('(SELECT COUNT(*) FROM users WHERE outlet_id = o.id) as staff_count'),
                DB::raw('(SELECT COUNT(*) FROM registers WHERE outlet_id = o.id) as registers_count')
            )
            ->orderBy('o.id', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:outlets,code',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'receipt_header' => 'nullable|string|max:255',
            'receipt_footer' => 'nullable|string|max:255',
        ]);

        $id = DB::table('outlets')->insertGetId([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'receipt_header' => $validated['receipt_header'] ?? 'Thank you for shopping!',
            'receipt_footer' => $validated['receipt_footer'] ?? 'Please come again!',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Store outlet created successfully.',
            'data' => ['id' => $id],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'receipt_header' => 'nullable|string|max:255',
            'receipt_footer' => 'nullable|string|max:255',
        ]);

        DB::table('outlets')->where('id', $id)->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'receipt_header' => $validated['receipt_header'] ?? null,
            'receipt_footer' => $validated['receipt_footer'] ?? null,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Store outlet updated successfully.',
        ]);
    }

    public function destroy($id)
    {
        DB::table('outlets')->where('id', $id)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Store outlet deactivated.',
        ]);
    }
}
