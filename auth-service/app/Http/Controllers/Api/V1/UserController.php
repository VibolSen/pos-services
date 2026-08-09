<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role');
        $search = $request->query('q');

        $query = DB::table('users as u')
            ->leftJoin('outlets as o', 'u.outlet_id', '=', 'o.id');

        if (!empty($role)) {
            $query->where('u.role', $role);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                  ->orWhere('u.email', 'like', "%{$search}%");
            });
        }

        $users = $query->select(
                'u.id',
                'u.name',
                'u.email',
                'u.role',
                'u.outlet_id',
                'u.pin_code',
                'u.is_active',
                'u.created_at',
                'o.name as outlet_name'
            )
            ->orderBy('u.id', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    public function permissions()
    {
        $matrix = [
            'super_admin' => [
                'name' => 'Super Admin',
                'capabilities' => ['sales.create', 'sales.discount', 'sales.void', 'sales.refund', 'catalog.manage', 'inventory.adjust', 'reports.view', 'users.manage', 'settings.manage'],
            ],
            'admin' => [
                'name' => 'Administrator',
                'capabilities' => ['sales.create', 'sales.discount', 'sales.void', 'sales.refund', 'catalog.manage', 'inventory.adjust', 'reports.view', 'users.manage'],
            ],
            'outlet_manager' => [
                'name' => 'Outlet Manager',
                'capabilities' => ['sales.create', 'sales.discount', 'sales.void', 'sales.refund', 'catalog.view', 'inventory.adjust', 'reports.view'],
            ],
            'supervisor' => [
                'name' => 'Supervisor',
                'capabilities' => ['sales.create', 'sales.discount', 'sales.void', 'sales.refund', 'catalog.view', 'inventory.count'],
            ],
            'cashier' => [
                'name' => 'Cashier',
                'capabilities' => ['sales.create', 'receipts.print', 'shifts.manage'],
            ],
            'inventory_clerk' => [
                'name' => 'Inventory Clerk',
                'capabilities' => ['catalog.manage', 'inventory.adjust', 'inventory.transfer', 'inventory.count'],
            ],
            'accountant' => [
                'name' => 'Accountant',
                'capabilities' => ['reports.view', 'reconciliation.manage', 'expenses.view'],
            ],
            'customer' => [
                'name' => 'Customer',
                'capabilities' => ['catalog.view', 'orders.place', 'profile.manage'],
            ],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $matrix,
        ]);
    }

    public function store(Request $request)
    {
        $allowedRoles = [
            'super_admin', 'admin', 'outlet_manager', 'supervisor',
            'cashier', 'inventory_clerk', 'accountant', 'customer'
        ];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'outlet_id' => 'nullable|integer',
            'pin_code' => 'nullable|string|max:10',
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'pin_code' => $validated['pin_code'] ?? null,
            'role' => $validated['role'],
            'outlet_id' => $validated['outlet_id'] ?? 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User account created successfully.',
            'data' => ['id' => $userId],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $allowedRoles = [
            'super_admin', 'admin', 'outlet_manager', 'supervisor',
            'cashier', 'inventory_clerk', 'accountant', 'customer'
        ];

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'password' => 'nullable|string|min:6',
            'role' => ['sometimes', 'string', Rule::in($allowedRoles)],
            'outlet_id' => 'sometimes|nullable|integer',
            'pin_code' => 'sometimes|nullable|string|max:10',
            'is_active' => 'sometimes|boolean',
        ]);

        $updateData = [];
        if (isset($validated['name'])) $updateData['name'] = $validated['name'];
        if (isset($validated['email'])) $updateData['email'] = $validated['email'];
        if (isset($validated['role'])) $updateData['role'] = $validated['role'];
        if (array_key_exists('outlet_id', $validated)) $updateData['outlet_id'] = $validated['outlet_id'];
        if (array_key_exists('pin_code', $validated)) $updateData['pin_code'] = $validated['pin_code'];
        if (isset($validated['is_active'])) $updateData['is_active'] = $validated['is_active'];
        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }
        $updateData['updated_at'] = now();

        DB::table('users')->where('id', $id)->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'User account updated successfully.',
        ]);
    }

    public function resetPassword(Request $request, $id)
    {
        $validated = $request->validate([
            'new_password' => 'required|string|min:6',
        ]);

        DB::table('users')->where('id', $id)->update([
            'password' => Hash::make($validated['new_password']),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User password reset successfully.',
        ]);
    }

    public function verifyPin(Request $request)
    {
        $validated = $request->validate([
            'pin_code' => 'required|string',
        ]);

        $supervisor = DB::table('users')
            ->where('pin_code', $validated['pin_code'])
            ->where('is_active', true)
            ->whereIn('role', ['super_admin', 'admin', 'outlet_manager', 'supervisor'])
            ->first();

        if (!$supervisor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid authorization PIN code or unauthorized role.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'PIN authorized successfully.',
            'data' => [
                'user_id' => $supervisor->id,
                'user_name' => $supervisor->name,
                'role' => $supervisor->role,
            ],
        ]);
    }

    public function destroy($id)
    {
        DB::table('users')->where('id', $id)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User account deactivated.',
        ]);
    }
}
