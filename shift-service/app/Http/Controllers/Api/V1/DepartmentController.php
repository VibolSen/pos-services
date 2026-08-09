<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('q');

        $query = DB::table('departments as d');

        if (!empty($search)) {
            $query->where('d.name', 'like', "%{$search}%")
                  ->orWhere('d.code', 'like', "%{$search}%");
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code',
            'description' => 'nullable|string|max:500',
        ]);

        $id = DB::table('departments')->insertGetId([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        DB::table('departments')->where('id', $id)->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Department updated successfully.',
        ]);
    }

    public function destroy($id)
    {
        DB::table('departments')->where('id', $id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Department deleted successfully.',
        ]);
    }
}
