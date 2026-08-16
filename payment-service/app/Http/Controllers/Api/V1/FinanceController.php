<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    public function expenses(Request $request)
    {
        $this->ensureExpensesSeeded();
        $expenses = DB::table('expenses')->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $expenses]);
    }

    public function incomes(Request $request)
    {
        $this->ensureIncomesSeeded();
        $incomes = DB::table('incomes')->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $incomes]);
    }

    public function bankAccounts(Request $request)
    {
        $this->ensureBankAccountsSeeded();
        $accounts = DB::table('bank_accounts')->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $accounts]);
    }

    protected function ensureExpensesSeeded()
    {
        if (DB::table('expenses')->count() === 0) {
            DB::table('expenses')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'expense_ref' => 'EXP-001',
                    'category' => 'Utilities & Power',
                    'description' => 'EDC Electricity Monthly Bill',
                    'amount' => 320.00,
                    'date_paid' => '2026-08-05',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'expense_ref' => 'EXP-002',
                    'category' => 'Store Maintenance',
                    'description' => 'Air conditioner cleaning & servicing',
                    'amount' => 85.00,
                    'date_paid' => '2026-08-08',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    protected function ensureIncomesSeeded()
    {
        if (DB::table('incomes')->count() === 0) {
            DB::table('incomes')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'income_ref' => 'INC-001',
                    'source' => 'Catering Event Deposit',
                    'description' => 'Wedding catering advance payment',
                    'amount' => 800.00,
                    'date_received' => '2026-08-07',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'income_ref' => 'INC-002',
                    'source' => 'Recycling & Scrap',
                    'description' => 'Used coffee grounds bulk sale',
                    'amount' => 45.00,
                    'date_received' => '2026-08-10',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    protected function ensureBankAccountsSeeded()
    {
        if (DB::table('bank_accounts')->count() === 0) {
            DB::table('bank_accounts')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'bank_name' => 'ABA Bank',
                    'account_name' => 'Dreams Coffee POS Outlet 01',
                    'account_number' => '000 123 456',
                    'currency' => 'USD',
                    'status' => 'connected',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'bank_name' => 'ACLEDA Bank',
                    'account_name' => 'Dreams Coffee Bakery KHR',
                    'account_number' => '001 987 654',
                    'currency' => 'KHR',
                    'status' => 'connected',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'bank_name' => 'NBC Bakong Open API Engine',
                    'account_name' => 'Merchant Bakong Account',
                    'account_number' => 'bakong_merchant_001@acleda',
                    'currency' => 'USD / KHR',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}
