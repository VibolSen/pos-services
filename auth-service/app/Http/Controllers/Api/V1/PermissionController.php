<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    /**
     * List all available system permissions grouped by module.
     */
    public function index(Request $request)
    {
        $permissions = DB::table('permissions')
            ->orderBy('group', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        $grouped = [];
        foreach ($permissions as $perm) {
            $grouped[$perm->group][] = $perm;
        }

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'grouped' => $grouped,
        ]);
    }
}
