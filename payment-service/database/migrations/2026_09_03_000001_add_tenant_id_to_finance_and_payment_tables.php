<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'expenses',
            'incomes',
            'bank_accounts',
            'reconciliations',
            'reconciliation_exceptions',
            'payments',
            'payment_attempts',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $tableBlueprint) {
                    $tableBlueprint->string('tenant_id', 36)->nullable()->index()->after('id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'expenses',
            'incomes',
            'bank_accounts',
            'reconciliations',
            'reconciliation_exceptions',
            'payments',
            'payment_attempts',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $tableBlueprint) {
                    $tableBlueprint->dropColumn('tenant_id');
                });
            }
        }
    }
};
