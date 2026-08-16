<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditLogController extends Controller
{
    /**
     * Get system audit trail logs
     */
    public function index(Request $request)
    {
        $module = $request->query('module');

        $query = DB::table('audit_logs')->orderBy('created_at', 'desc');

        if (!empty($module) && $module !== 'all') {
            $query->where('module', $module);
        }

        $logs = $query->limit(100)->get();

        return response()->json([
            'status' => 'success',
            'data' => $logs,
        ]);
    }

    /**
     * Static Helper method to record audit events across services
     */
    public static function log($userId, string $userName, string $action, string $module = 'system', ?string $ipAddress = null, $payload = null)
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'user_name' => $userName,
            'action' => $action,
            'module' => $module,
            'ip_address' => $ipAddress ?? request()->ip(),
            'payload' => $payload ? json_encode($payload) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
