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
        if (Schema::hasTable('departments') && !Schema::hasColumn('departments', 'tenant_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->string('tenant_id', 36)->nullable()->index()->after('id');
            });
        }

        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', 'tenant_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('tenant_id', 36)->nullable()->index()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('departments') && Schema::hasColumn('departments', 'tenant_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'tenant_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
