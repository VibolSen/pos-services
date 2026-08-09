<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $outletId = $request->query('outlet_id', 1);

        // 1. Total Sales
        $totalSales = DB::table('sales')
            ->where('outlet_id', $outletId)
            ->where('status', 'completed')
            ->sum('grand_total');

        // 2. Total Sales Returns
        $totalSalesReturn = DB::table('refunds')
            ->sum('amount');

        // 3. Simulated/Tracked Purchases
        $totalPurchase = 24145789.00;
        $totalPurchaseReturn = 18458747.00;

        // 4. Low Stock Count
        $lowStockCount = DB::table('inventory_balances')
            ->where('outlet_id', $outletId)
            ->where('on_hand', '<=', 10)
            ->count();

        // 5. Total Customers Count
        $totalCustomers = DB::table('users')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'metrics' => [
                    'total_sales' => (float)$totalSales ?: 48988078.00,
                    'total_sales_change' => '+22%',
                    'total_sales_return' => (float)$totalSalesReturn ?: 16478145.00,
                    'total_sales_return_change' => '-22%',
                    'total_purchase' => $totalPurchase,
                    'total_purchase_change' => '+22%',
                    'total_purchase_return' => $totalPurchaseReturn,
                    'total_purchase_return_change' => '-22%',
                    'profit' => 8458798.00,
                    'profit_change' => '+35%',
                    'invoice_due' => 48988.78,
                    'invoice_due_change' => '-19%',
                    'total_expenses' => 8980097.00,
                    'total_expenses_change' => '+41%',
                    'total_payment_returns' => 78458798.00,
                    'total_payment_returns_change' => '-20%',
                ],
                'counts' => [
                    'low_stock' => $lowStockCount,
                    'suppliers' => 6987,
                    'customers' => $totalCustomers ?: 4896,
                    'orders' => DB::table('sales')->count() ?: 487,
                ]
            ]
        ]);
    }

    public function widgets(Request $request)
    {
        $outletId = $request->query('outlet_id', 1);

        // 1. Top Selling Products
        $topSelling = DB::table('sale_lines as sl')
            ->join('products as p', 'sl.product_id', '=', 'p.id')
            ->select(
                'p.id',
                'p.name',
                'p.sku',
                'p.selling_price as price',
                'p.image_url',
                DB::raw('SUM(sl.quantity) as sales_count'),
                DB::raw('SUM(sl.subtotal) as total_revenue')
            )
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.selling_price', 'p.image_url')
            ->orderByDesc('sales_count')
            ->limit(5)
            ->get();

        // Fallback default sample data if no sales yet
        if ($topSelling->isEmpty()) {
            $topSelling = collect([
                ['id' => 1, 'name' => 'Charger Cable - Lightning', 'price' => 187.00, 'sales_count' => 247, 'change' => '+25%'],
                ['id' => 2, 'name' => 'Yves Saint Eau De Parfum', 'price' => 145.00, 'sales_count' => 289, 'change' => '+25%'],
                ['id' => 3, 'name' => 'Apple Airpods 2', 'price' => 450.00, 'sales_count' => 300, 'change' => '+25%'],
                ['id' => 4, 'name' => 'Vacuum Cleaner', 'price' => 139.00, 'sales_count' => 225, 'change' => '+21%'],
                ['id' => 5, 'name' => 'Samsung Galaxy S21 Fe 5g', 'price' => 898.00, 'sales_count' => 365, 'change' => '+25%'],
            ]);
        }

        // 2. Low Stock Products
        $lowStockProducts = DB::table('inventory_balances as ib')
            ->join('products as p', 'ib.product_id', '=', 'p.id')
            ->where('ib.outlet_id', $outletId)
            ->select('p.id', 'p.name', 'p.sku', 'ib.on_hand as in_stock')
            ->orderBy('ib.on_hand', 'asc')
            ->limit(5)
            ->get();

        if ($lowStockProducts->isEmpty()) {
            $lowStockProducts = collect([
                ['id' => 1, 'name' => 'Vacuum Cleaner Robot', 'sku' => '#940004', 'in_stock' => 21],
                ['id' => 2, 'name' => 'Dell XPS 13', 'sku' => '#605814', 'in_stock' => 8],
                ['id' => 3, 'name' => 'KitchenAid Stand Mixer', 'sku' => '#325569', 'in_stock' => 14],
                ['id' => 4, 'name' => 'Levi\'s Trucker Jacket', 'sku' => '#124588', 'in_stock' => 12],
                ['id' => 5, 'name' => 'Lay\'s Classic', 'sku' => '#305586', 'in_stock' => 10],
            ]);
        }

        // 3. Recent Sales / Transactions
        $recentSales = DB::table('sales as s')
            ->join('users as u', 's.user_id', '=', 'u.id')
            ->select('s.id', 's.receipt_number', 'u.name as customer', 's.grand_total', 's.status', 's.created_at')
            ->orderByDesc('s.id')
            ->limit(5)
            ->get();

        if ($recentSales->isEmpty()) {
            $recentSales = collect([
                ['id' => 1, 'date' => '24 May 2026', 'customer' => 'Andrea Willer', 'customer_id' => '#114589', 'status' => 'Completed', 'total' => 4560.00],
                ['id' => 2, 'date' => '23 May 2026', 'customer' => 'Timothy Sands', 'customer_id' => '#114589', 'status' => 'Completed', 'total' => 3569.00],
                ['id' => 3, 'date' => '22 May 2026', 'customer' => 'Bonnie Rodrigues', 'customer_id' => '#114589', 'status' => 'Draft', 'total' => 2659.00],
                ['id' => 4, 'date' => '21 May 2026', 'customer' => 'Randy McCree', 'customer_id' => '#114589', 'status' => 'Completed', 'total' => 2155.00],
            ]);
        }

        // 4. Top Customers
        $topCustomers = collect([
            ['name' => 'Carlos Curran', 'country' => 'USA', 'orders' => 24, 'spent' => 8965.00],
            ['name' => 'Stan Gaunter', 'country' => 'UAE', 'orders' => 22, 'spent' => 6985.00],
            ['name' => 'Richard Wilson', 'country' => 'Germany', 'orders' => 14, 'spent' => 5366.00],
            ['name' => 'Mary Bronson', 'country' => 'Belgium', 'orders' => 8, 'spent' => 4569.00],
            ['name' => 'Annie Tremblay', 'country' => 'Greenland', 'orders' => 14, 'spent' => 35698.00],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'top_selling' => $topSelling,
                'low_stock' => $lowStockProducts,
                'recent_sales' => $recentSales,
                'top_customers' => $topCustomers,
            ]
        ]);
    }

    public function charts()
    {
        // Monthly chart comparisons
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'July', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        $salesPurchasesChart = [];
        foreach ($months as $m) {
            $salesPurchasesChart[] = [
                'month' => $m,
                'purchases' => rand(15, 55),
                'sales' => rand(20, 60),
            ];
        }

        $salesStatics = [];
        foreach ($months as $m) {
            $salesStatics[] = [
                'month' => $m,
                'revenue' => rand(10, 30),
                'expense' => rand(-25, -5),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'sales_purchases' => $salesPurchasesChart,
                'sales_statics' => $salesStatics,
                'top_categories' => [
                    ['name' => 'Electronics', 'percentage' => 50, 'sales' => 698],
                    ['name' => 'Sports', 'percentage' => 26, 'sales' => 545],
                    ['name' => 'Lifestyles', 'percentage' => 24, 'sales' => 456],
                ]
            ]
        ]);
    }
}
