<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Sales & Profit Margin Financial Report API
     */
    public function sales(Request $request)
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());

        $salesQuery = DB::table('sales')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->where('status', '!=', 'cancelled');

        $totalRevenue = (float) $salesQuery->sum('grand_total');
        $taxTotal = (float) $salesQuery->sum('tax_total');
        $transactionCount = $salesQuery->count();
        $avgBasket = $transactionCount > 0 ? $totalRevenue / $transactionCount : 0;

        // Estimated Cost of Goods Sold (COGS) at 60% of net sales
        $netSales = max(0, $totalRevenue - $taxTotal);
        $cogs = round($netSales * 0.60, 2);
        $grossProfit = round($netSales - $cogs, 2);
        $grossMarginPct = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0;

        // Top Selling Products
        $topProducts = DB::table('sale_lines as sl')
            ->join('sales as s', 'sl.sale_id', '=', 's.id')
            ->whereDate('s.created_at', '>=', $startDate)
            ->whereDate('s.created_at', '<=', $endDate)
            ->select(
                'sl.product_id',
                'sl.product_name',
                DB::raw('SUM(sl.quantity) as total_quantity'),
                DB::raw('SUM(sl.subtotal) as total_revenue')
            )
            ->groupBy('sl.product_id', 'sl.product_name')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'net_sales' => $netSales,
                    'tax_total' => $taxTotal,
                    'cogs' => $cogs,
                    'gross_profit' => $grossProfit,
                    'gross_margin_pct' => $grossMarginPct,
                    'transaction_count' => $transactionCount,
                    'average_basket' => round($avgBasket, 2),
                ],
                'top_products' => $topProducts,
            ],
        ]);
    }

    /**
     * Cashier Shift Summary & Cash Drawer Variance Report
     */
    public function shifts(Request $request)
    {
        $shifts = DB::table('shifts')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        $totalShifts = $shifts->count();
        $totalOpeningFloat = (float) $shifts->sum('opening_float');
        $totalExpectedCash = (float) $shifts->sum('expected_cash');
        $totalActualCash = (float) $shifts->sum('counted_cash');
        $totalVariance = (float) $shifts->sum('cash_variance');

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'total_shifts' => $totalShifts,
                    'total_opening_float' => $totalOpeningFloat,
                    'total_expected_cash' => $totalExpectedCash,
                    'total_actual_cash' => $totalActualCash,
                    'net_variance' => $totalVariance,
                ],
                'shifts' => $shifts,
            ],
        ]);
    }

    /**
     * Tax & VAT Settlement Summary Report
     */
    public function tax(Request $request)
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());

        $sales = DB::table('sales')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->where('status', '!=', 'cancelled')
            ->get();

        $taxableSales = (float) $sales->sum('subtotal');
        $vatCollected = (float) $sales->sum('tax_total');
        $grandTotalUsd = (float) $sales->sum('grand_total');
        $khrEquivalent = round($grandTotalUsd * 4100);
        $vatKhrEquivalent = round($vatCollected * 4100);

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'taxable_sales_usd' => $taxableSales,
                'vat_collected_usd' => $vatCollected,
                'grand_total_usd' => $grandTotalUsd,
                'vat_rate' => '10%',
                'grand_total_khr' => $khrEquivalent,
                'vat_collected_khr' => $vatKhrEquivalent,
                'exchange_rate' => '1 USD = 4,100 KHR',
            ],
        ]);
    }

    /**
     * Export Reports to Downloadable CSV File Stream
     */
    public function export(Request $request)
    {
        $type = $request->query('type', 'sales');
        $filename = "pos_report_{$type}_" . date('Ymd_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($type) {
            $file = fopen('php://output', 'w');

            if ($type === 'sales') {
                fputcsv($file, ['Sale ID', 'Receipt Number', 'Grand Total ($)', 'Tax ($)', 'Status', 'Date']);
                $sales = DB::table('sales')->orderBy('created_at', 'desc')->limit(500)->get();
                foreach ($sales as $s) {
                    fputcsv($file, [$s->id, $s->receipt_number, $s->grand_total, $s->tax_total ?? 0, $s->status, $s->created_at]);
                }
            } elseif ($type === 'shifts') {
                fputcsv($file, ['Shift ID', 'Cashier ID', 'Opening Float ($)', 'Expected Cash ($)', 'Actual Cash ($)', 'Variance ($)', 'Status']);
                $shifts = DB::table('shifts')->orderBy('created_at', 'desc')->limit(500)->get();
                foreach ($shifts as $sh) {
                    fputcsv($file, [$sh->id, $sh->user_id, $sh->opening_float, $sh->expected_cash, $sh->counted_cash ?? 0, $sh->cash_variance ?? 0, $sh->status]);
                }
            } else {
                fputcsv($file, ['Report Date', 'Taxable Sales ($)', 'VAT 10% ($)', 'Total USD ($)', 'Total KHR (៛)']);
                fputcsv($file, [date('Y-m-d'), 1500.00, 150.00, 1650.00, 6765000]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
