<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function active(Request $request)
    {
        $userId = $request->user()->id;

        $shift = DB::table('shifts')
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->orderBy('id', 'desc')
            ->first();

        if (!$shift) {
            return response()->json([
                'status' => 'success',
                'data' => null,
            ]);
        }

        $cashSales = DB::table('sales as s')
            ->join('payments as p', 's.id', '=', 'p.sale_id')
            ->where('s.shift_id', $shift->id)
            ->where('p.payment_method', 'cash')
            ->where('p.status', 'paid')
            ->sum('p.amount');

        $cashIn = DB::table('cash_drawer_movements')
            ->where('shift_id', $shift->id)
            ->where('type', 'in')
            ->sum('amount');

        $cashOut = DB::table('cash_drawer_movements')
            ->where('shift_id', $shift->id)
            ->where('type', 'out')
            ->sum('amount');

        $expectedCash = $shift->opening_float + $cashSales + $cashIn - $cashOut;

        return response()->json([
            'status' => 'success',
            'data' => [
                'shift' => $shift,
                'summary' => [
                    'opening_float' => (float)$shift->opening_float,
                    'cash_sales' => (float)$cashSales,
                    'cash_in' => (float)$cashIn,
                    'cash_out' => (float)$cashOut,
                    'expected_cash' => (float)$expectedCash,
                ],
            ],
        ]);
    }

    public function open(Request $request)
    {
        $user = $request->user();

        // Check if user already has an active shift
        $existing = DB::table('shifts')
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'You already have an active open shift.',
                'data' => $existing,
            ], 422);
        }

        $validated = $request->validate([
            'opening_float' => 'required|numeric|min:0',
            'outlet_id' => 'nullable|string',
            'register_id' => 'nullable|string',
        ]);

        $outletId = $validated['outlet_id'] ?? $user->outlet_id ?? 1;
        $registerId = $validated['register_id'] ?? 1;
        $shiftId = (string) \Illuminate\Support\Str::uuid();

        DB::table('shifts')->insert([
            'id' => $shiftId,
            'outlet_id' => $outletId,
            'register_id' => $registerId,
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_float' => $validated['opening_float'],
            'expected_cash' => $validated['opening_float'],
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shift = DB::table('shifts')->where('id', $shiftId)->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Shift opened successfully.',
            'data' => $shift,
        ], 201);
    }

    public function cashMovement(Request $request, $id)
    {
        $validated = $request->validate([
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        $shift = DB::table('shifts')->where('id', $id)->first();
        if (!$shift || $shift->status !== 'open') {
            return response()->json(['status' => 'error', 'message' => 'Active open shift not found.'], 404);
        }

        $movementId = (string) \Illuminate\Support\Str::uuid();

        DB::table('cash_drawer_movements')->insert([
            'id' => $movementId,
            'shift_id' => $shift->id,
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'reason' => $validated['reason'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cash drawer movement recorded.',
            'data' => ['id' => $movementId],
        ]);
    }

    public function close(Request $request, $id)
    {
        $validated = $request->validate([
            'counted_cash' => 'required|numeric|min:0',
            'closing_note' => 'nullable|string',
        ]);

        $shift = DB::table('shifts')->where('id', $id)->first();
        if (!$shift || $shift->status !== 'open') {
            return response()->json(['status' => 'error', 'message' => 'Active open shift not found.'], 404);
        }

        $cashSales = DB::table('sales as s')
            ->join('payments as p', 's.id', '=', 'p.sale_id')
            ->where('s.shift_id', $shift->id)
            ->where('p.payment_method', 'cash')
            ->where('p.status', 'paid')
            ->sum('p.amount');

        $cashIn = DB::table('cash_drawer_movements')->where('shift_id', $shift->id)->where('type', 'in')->sum('amount');
        $cashOut = DB::table('cash_drawer_movements')->where('shift_id', $shift->id)->where('type', 'out')->sum('amount');

        $expectedCash = $shift->opening_float + $cashSales + $cashIn - $cashOut;
        $countedCash = (float)$validated['counted_cash'];
        $cashVariance = $countedCash - $expectedCash;

        // Require Supervisor PIN authorization if cash variance exceeds threshold ($5.00)
        if (abs($cashVariance) > 5.00) {
            $supervisorPin = $request->input('supervisor_pin');
            $userRole = $request->user()->role ?? 'cashier';
            
            if (!in_array($userRole, ['supervisor', 'outlet_manager', 'admin', 'super_admin'])) {
                if (empty($supervisorPin)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cash drawer variance exceeds threshold ($5.00). Supervisor PIN authorization is required.',
                        'cash_variance' => $cashVariance,
                    ], 403);
                }

                $supervisor = DB::table('users')
                    ->where('pin_code', $supervisorPin)
                    ->whereIn('role', ['supervisor', 'outlet_manager', 'admin', 'super_admin'])
                    ->first();

                if (!$supervisor) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid Supervisor PIN code.',
                    ], 403);
                }
            }
        }

        DB::table('shifts')->where('id', $shift->id)->update([
            'closed_at' => now(),
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
            'cash_variance' => $cashVariance,
            'status' => 'closed',
            'closing_note' => $validated['closing_note'] ?? null,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Shift closed successfully.',
            'data' => [
                'shift_id' => $shift->id,
                'expected_cash' => $expectedCash,
                'counted_cash' => $countedCash,
                'cash_variance' => $cashVariance,
            ],
        ]);
    }
}
