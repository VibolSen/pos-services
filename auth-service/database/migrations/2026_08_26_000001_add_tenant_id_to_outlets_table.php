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
        if (Schema::hasTable('outlets') && !Schema::hasColumn('outlets', 'tenant_id')) {
            Schema::table('outlets', function (Blueprint $table) {
                $table->uuid('tenant_id')->nullable()->after('id')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('outlets') && Schema::hasColumn('outlets', 'tenant_id')) {
            Schema::table('outlets', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
