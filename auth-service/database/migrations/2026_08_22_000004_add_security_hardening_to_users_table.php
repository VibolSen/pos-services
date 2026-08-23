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
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                    $table->unsignedInteger('failed_login_attempts')->default(0)->after('pin_code');
                }
                if (!Schema::hasColumn('users', 'lockout_until')) {
                    $table->timestamp('lockout_until')->nullable()->after('failed_login_attempts');
                }
                if (!Schema::hasColumn('users', 'two_factor_secret')) {
                    $table->string('two_factor_secret')->nullable()->after('lockout_until');
                }
                if (!Schema::hasColumn('users', 'two_factor_enabled')) {
                    $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = array_filter(['failed_login_attempts', 'lockout_until', 'two_factor_secret', 'two_factor_enabled'], function ($col) {
                    return Schema::hasColumn('users', $col);
                });
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
