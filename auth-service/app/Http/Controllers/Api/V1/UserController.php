<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $role = $request->query('role');
        $search = $request->query('q') ?? $request->query('search');
        $scope = $request->query('scope'); // 'staff' | 'all'

        $query = DB::table('users as u')
            ->leftJoin('outlets as o', 'u.outlet_id', '=', 'o.id');

        // Scope to tenant organization
        if ($currentUser && !empty($currentUser->tenant_id)) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('u.tenant_id', $currentUser->tenant_id)
                  ->orWhereNull('u.tenant_id'); // Global administrative fallbacks
            });
        }

        if (!empty($role) && $role !== 'all') {
            $query->where('u.role', $role);
        }

        if ($scope === 'staff' || $request->has('staff_only')) {
            $query->whereNotIn('u.role', ['customer', 'client', 'guest']);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                  ->orWhere('u.email', 'like', "%{$search}%");
            });
        }

        $users = $query->select(
                'u.id',
                'u.tenant_id',
                'u.name',
                'u.email',
                'u.phone',
                'u.role',
                'u.outlet_id',
                'u.pin_code',
                'u.is_active',
                'u.created_at',
                'o.name as outlet_name'
            )
            ->orderBy('u.created_at', 'desc')
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
            'user' => [
                'name' => 'User',
                'capabilities' => ['catalog.view', 'profile.manage'],
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
        $dynamicRoleSlugs = DB::table('roles')->pluck('slug')->toArray();
        $allowedRoles = array_unique(array_merge([
            'super_admin', 'admin', 'administrator', 'outlet_manager', 'supervisor',
            'cashier', 'inventory_clerk', 'accountant', 'user', 'customer'
        ], $dynamicRoleSlugs));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'outlet_id' => 'nullable|string',
            'pin_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:30',
        ]);

        $currentUser = $request->user();
        $tenantId = $currentUser?->tenant_id;

        // Quota check
        if ($currentUser && $currentUser->role !== 'super_admin') {
            $tenant = $tenantId ? DB::table('tenants')->where('id', $tenantId)->first() : null;
            $maxUsers = $tenant->max_users ?? 50;
            $currentUsersCount = DB::table('users')->where('role', '!=', 'super_admin')->count();

            if ($currentUsersCount >= $maxUsers) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'QUOTA_EXCEEDED',
                    'message' => "User limit reached ({$currentUsersCount}/{$maxUsers}) for your subscription tier. Please upgrade your plan.",
                ], 403);
            }
        }

        $userId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $userId,
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'pin_code' => $validated['pin_code'] ?? '1234',
            'role' => $validated['role'],
            'outlet_id' => $validated['outlet_id'] ?? null,
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

    /**
     * Create a Staff Invitation Link.
     * POST /api/v1/users/invite
     */
    public function invite(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|string',
            'outlet_id' => 'nullable|string',
        ]);

        // Check if user already exists
        if (DB::table('users')->where('email', $validated['email'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'A user with this email address already exists in the system.',
            ], 422);
        }

        $currentUser = $request->user();
        $tenantId = $currentUser?->tenant_id;

        // Quota check
        if ($currentUser && $currentUser->role !== 'super_admin') {
            $tenant = $tenantId ? DB::table('tenants')->where('id', $tenantId)->first() : null;
            $maxUsers = $tenant->max_users ?? 50;
            $currentUsersCount = DB::table('users')->where('role', '!=', 'super_admin')->count();

            if ($currentUsersCount >= $maxUsers) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'QUOTA_EXCEEDED',
                    'message' => "User limit reached ({$currentUsersCount}/{$maxUsers}) for your subscription tier. Please upgrade your plan.",
                ], 403);
            }
        }

        $inviteToken = Str::random(48);
        $inviteId = (string) Str::uuid();

        // Expire any previous invitations for this email
        DB::table('user_invitations')
            ->where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->delete();

        DB::table('user_invitations')->insert([
            'id' => $inviteId,
            'tenant_id' => $tenantId,
            'outlet_id' => $validated['outlet_id'] ?? null,
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => $inviteToken,
            'invited_by' => $currentUser?->id,
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inviteUrl = "/accept-invite?token={$inviteToken}";

        return response()->json([
            'status' => 'success',
            'message' => 'Staff invitation link generated successfully.',
            'data' => [
                'id' => $inviteId,
                'email' => $validated['email'],
                'role' => $validated['role'],
                'token' => $inviteToken,
                'invite_url' => $inviteUrl,
                'expires_at' => now()->addDays(7)->toIso8601String(),
            ],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $dynamicRoleSlugs = DB::table('roles')->pluck('slug')->toArray();
        $allowedRoles = array_unique(array_merge([
            'super_admin', 'admin', 'administrator', 'outlet_manager', 'supervisor',
            'cashier', 'inventory_clerk', 'accountant', 'user', 'customer'
        ], $dynamicRoleSlugs));

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'phone' => 'sometimes|nullable|string|max:30',
            'password' => 'nullable|string|min:6',
            'role' => ['sometimes', 'string', Rule::in($allowedRoles)],
            'outlet_id' => 'sometimes|nullable|string',
            'pin_code' => 'sometimes|nullable|string|max:10',
            'is_active' => 'sometimes|boolean',
        ]);

        $updateData = [];
        if (isset($validated['name'])) $updateData['name'] = $validated['name'];
        if (isset($validated['email'])) $updateData['email'] = $validated['email'];
        if (isset($validated['phone'])) $updateData['phone'] = $validated['phone'];
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
