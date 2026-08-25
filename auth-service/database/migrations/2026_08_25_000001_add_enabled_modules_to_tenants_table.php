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
        if (Schema::hasTable('tenants') && !Schema::hasColumn('tenants', 'enabled_modules')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->json('enabled_modules')->nullable()->after('currency');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'enabled_modules')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('enabled_modules');
            });
        }
    }
};
