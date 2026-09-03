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
            ->where('p.tender_type', 'cash')
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
            ->where('p.tender_type', 'cash')
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

    public function xReport(Request $request, $id)
    {
        $shift = DB::table('shifts as s')
            ->leftJoin('users as u', 's.user_id', '=', 'u.id')
            ->where('s.id', $id)
            ->select('s.*', 'u.name as cashier_name')
            ->first();

        if (!$shift) {
            return response()->json(['status' => 'error', 'message' => 'Shift not found.'], 404);
        }

        $cashSales = (float) DB::table('sales as s')
            ->join('payments as p', 's.id', '=', 'p.sale_id')
            ->where('s.shift_id', $shift->id)
            ->where('p.tender_type', 'cash')
            ->where('p.status', 'paid')
            ->sum('p.amount');

        $khqrSales = (float) DB::table('sales as s')
            ->join('payments as p', 's.id', '=', 'p.sale_id')
            ->where('s.shift_id', $shift->id)
            ->where('p.tender_type', 'khqr')
            ->where('p.status', 'paid')
            ->sum('p.amount');

        $cardSales = (float) DB::table('sales as s')
            ->join('payments as p', 's.id', '=', 'p.sale_id')
            ->where('s.shift_id', $shift->id)
            ->where('p.tender_type', 'card')
            ->where('p.status', 'paid')
            ->sum('p.amount');

        $cashIn = (float) DB::table('cash_drawer_movements')->where('shift_id', $shift->id)->where('type', 'in')->sum('amount');
        $cashOut = (float) DB::table('cash_drawer_movements')->where('shift_id', $shift->id)->where('type', 'out')->sum('amount');

        $expectedCash = (float) $shift->opening_float + $cashSales + $cashIn - $cashOut;

        return response()->json([
            'status' => 'success',
            'data' => [
                'type' => 'X-REPORT',
                'report_code' => 'X-' . substr($shift->id, 0, 8),
                'shift' => $shift,
                'opening_float' => (float) $shift->opening_float,
                'cash_sales' => $cashSales,
                'khqr_sales' => $khqrSales,
                'card_sales' => $cardSales,
                'gross_sales' => $cashSales + $khqrSales + $cardSales,
                'pay_ins' => $cashIn,
                'pay_outs' => $cashOut,
                'expected_cash' => $expectedCash,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    public function history(Request $request)
    {
        $shifts = DB::table('shifts as s')
            ->leftJoin('users as u', 's.user_id', '=', 'u.id')
            ->leftJoin('outlets as o', 's.outlet_id', '=', 'o.id')
            ->select(
                's.*',
                'u.name as cashier_name',
                'o.name as outlet_name'
            )
            ->orderBy('s.created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'shifts' => $shifts,
            ],
        ]);
    }
}
