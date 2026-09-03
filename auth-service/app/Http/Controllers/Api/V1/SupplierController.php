<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierController extends Controller
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
        $search = $request->query('q');

        $query = DB::table('suppliers');

        // Scope to tenant organization
        if (!empty($tenantId) && $tenantId !== 'all') {
            $hasTenant = DB::table('suppliers')->where('tenant_id', $tenantId)->exists();
            if ($hasTenant) {
                $query->where('tenant_id', $tenantId);
            } else {
                $query->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                });
            }
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
        ]);

        $code = 'SUP-' . strtoupper(substr(uniqid(), -6));
        $id = (string) Str::uuid();

        DB::table('suppliers')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'code' => $code,
            'contact_name' => $validated['contact_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier created successfully.',
            'data' => ['id' => $id],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
        ]);

        DB::table('suppliers')->where('id', $id)->update([
            'name' => $validated['name'],
            'contact_name' => $validated['contact_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier profile updated successfully.',
        ]);
    }

    public function destroy($id)
    {
        DB::table('suppliers')->where('id', $id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier profile deleted.',
        ]);
    }
}
