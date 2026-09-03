<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepartmentController extends Controller
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

        $query = DB::table('departments as d');

        if (!empty($tenantId) && $tenantId !== 'all') {
            $query->where('d.tenant_id', $tenantId);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('d.name', 'like', "%{$search}%")
                  ->orWhere('d.code', 'like', "%{$search}%");
            });
        }

        $departments = $query->select(
                'd.id',
                'd.name',
                'd.code',
                'd.description',
                'd.created_at',
                DB::raw('(SELECT COUNT(*) FROM employees WHERE department_id = d.id) as employees_count')
            )
            ->orderBy('d.name', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $departments,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $code = strtoupper(trim($validated['code']));

        // Validate code uniqueness scoped to tenant
        $existsQuery = DB::table('departments')->where('code', $code);
        if (!empty($tenantId) && $tenantId !== 'all') {
            $existsQuery->where('tenant_id', $tenantId);
        }
        if ($existsQuery->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => "Department code '{$code}' already exists in your organization.",
            ], 422);
        }

        $id = (string) Str::uuid();

        DB::table('departments')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'code' => $code,
            'description' => $validated['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Department created successfully.',
            'data' => ['id' => $id],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $query = DB::table('departments')->where('id', $id);
        if (!empty($tenantId) && $tenantId !== 'all') {
            $hasTenant = DB::table('departments')->where('id', $id)->where('tenant_id', $tenantId)->exists();
            if ($hasTenant) {
                $query->where('tenant_id', $tenantId);
            }
        }

        $query->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Department updated successfully.',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $query = DB::table('departments')->where('id', $id);

        if (!empty($tenantId) && $tenantId !== 'all') {
            $hasTenant = DB::table('departments')->where('id', $id)->where('tenant_id', $tenantId)->exists();
            if ($hasTenant) {
                $query->where('tenant_id', $tenantId);
            }
        }

        $query->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Department deleted successfully.',
        ]);
    }
}
