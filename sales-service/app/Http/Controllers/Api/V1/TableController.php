<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TableController extends Controller
{
    /**
     * Get Restaurant Floor Plan Tables
     */
    public function index(Request $request)
    {
        $this->ensureDefaultTablesSeeded();

        $tables = DB::table('restaurant_tables')->orderBy('name', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $tables,
        ]);
    }

    /**
     * Update Table Occupancy Status
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:vacant,occupied,reserved,bill_requested',
        ]);

        DB::table('restaurant_tables')
            ->where('id', $id)
            ->update([
                'status' => $validated['status'],
                'updated_at' => now(),
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Table status updated.',
        ]);
    }

    protected function ensureDefaultTablesSeeded()
    {
        if (DB::table('restaurant_tables')->count() === 0) {
            $defaultTables = [
                ['name' => 'Table #01', 'zone' => 'Main Dining', 'capacity' => 2, 'status' => 'vacant'],
                ['name' => 'Table #02', 'zone' => 'Main Dining', 'capacity' => 4, 'status' => 'occupied'],
                ['name' => 'Table #03', 'zone' => 'Main Dining', 'capacity' => 4, 'status' => 'bill_requested'],
                ['name' => 'Patio #01', 'zone' => 'Outdoor Patio', 'capacity' => 4, 'status' => 'reserved'],
                ['name' => 'Patio #02', 'zone' => 'Outdoor Patio', 'capacity' => 6, 'status' => 'vacant'],
                ['name' => 'VIP Suite 1', 'zone' => 'VIP Lounge', 'capacity' => 8, 'status' => 'vacant'],
            ];

            foreach ($defaultTables as $t) {
                DB::table('restaurant_tables')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $t['name'],
                    'zone' => $t['zone'],
                    'capacity' => $t['capacity'],
                    'status' => $t['status'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
