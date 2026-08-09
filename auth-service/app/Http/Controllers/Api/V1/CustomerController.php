<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('q');

        $query = DB::table('customers');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $customers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
        ]);

        $code = 'CUST-' . strtoupper(substr(uniqid(), -6));

        $id = DB::table('customers')->insertGetId([
            'name' => $validated['name'],
            'code' => $code,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'loyalty_points' => 0,
            'total_spent' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Customer account created successfully.',
            'data' => ['id' => $id],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'loyalty_points' => 'nullable|integer',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'updated_at' => now(),
        ];

        if (isset($validated['loyalty_points'])) {
            $updateData['loyalty_points'] = $validated['loyalty_points'];
        }

        DB::table('customers')->where('id', $id)->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Customer profile updated successfully.',
        ]);
    }

    public function destroy($id)
    {
        DB::table('customers')->where('id', $id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Customer profile deleted.',
        ]);
    }
}
