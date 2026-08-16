<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'loyalty_points')) {
                    $table->integer('loyalty_points')->default(0)->after('email');
                }
                if (!Schema::hasColumn('customers', 'store_credit')) {
                    $table->decimal('store_credit', 12, 2)->default(0.00)->after('loyalty_points');
                }
            });
        }

        if (!Schema::hasTable('customer_credit_ledgers')) {
            Schema::create('customer_credit_ledgers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id');
                $table->decimal('amount_change', 12, 2);
                $table->string('entry_type'); // deposit, refund_credit, checkout_use, points_conversion, adjustment
                $table->string('reference_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_ledgers');
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn(['loyalty_points', 'store_credit']);
            });
        }
    }
};
