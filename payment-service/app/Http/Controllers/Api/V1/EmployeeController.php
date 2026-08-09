<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('q');
        $departmentId = $request->query('department_id');

        $query = DB::table('employees as e')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
            ->leftJoin('outlets as o', 'e.outlet_id', '=', 'o.id');

        if (!empty($departmentId)) {
            $query->where('e.department_id', $departmentId);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw("CONCAT(e.first_name, ' ', e.last_name)"), 'like', "%{$search}%")
                  ->orWhere('e.employee_code', 'like', "%{$search}%")
                  ->orWhere('e.email', 'like', "%{$search}%")
                  ->orWhere('e.phone', 'like', "%{$search}%");
            });
        }

        $employees = $query->select(
                'e.id',
                'e.employee_code',
                'e.first_name',
                'e.last_name',
                DB::raw("CONCAT(e.first_name, ' ', e.last_name) as full_name"),
                'e.email',
                'e.phone',
                'e.designation',
                'e.employment_type',
                'e.hire_date',
                'e.salary',
                'e.is_active',
                'e.department_id',
                'd.name as department_name',
                'e.outlet_id',
                'o.name as outlet_name',
                'e.created_at'
            )
            ->orderBy('e.id', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'department_id' => 'nullable|integer|exists:departments,id',
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'designation' => 'required|string|max:255',
            'employment_type' => 'required|string|in:Full-time,Part-time,Contract',
            'hire_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
        ]);

        $code = 'EMP-' . rand(100, 999);

        $id = DB::table('employees')->insertGetId([
            'employee_code' => $code,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'outlet_id' => $validated['outlet_id'] ?? 1,
            'designation' => $validated['designation'],
            'employment_type' => $validated['employment_type'],
            'hire_date' => $validated['hire_date'] ?? now()->toDateString(),
            'salary' => $validated['salary'] ?? 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Employee profile created successfully.',
            'data' => ['id' => $id],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'department_id' => 'nullable|integer|exists:departments,id',
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'designation' => 'required|string|max:255',
            'employment_type' => 'required|string|in:Full-time,Part-time,Contract',
            'hire_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
        ]);

        DB::table('employees')->where('id', $id)->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'outlet_id' => $validated['outlet_id'] ?? null,
            'designation' => $validated['designation'],
            'employment_type' => $validated['employment_type'],
            'hire_date' => $validated['hire_date'] ?? null,
            'salary' => $validated['salary'] ?? 0,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Employee record updated successfully.',
        ]);
    }

    public function destroy($id)
    {
        DB::table('employees')->where('id', $id)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Employee record deactivated.',
        ]);
    }
}
