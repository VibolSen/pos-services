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
        // 1. Tenants table
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('company_code')->unique()->nullable();
                $table->enum('client_tier', ['free_personal', 'business_runner', 'enterprise_org'])->default('free_personal');
                $table->enum('status', ['active', 'suspended', 'trial', 'expired'])->default('trial');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('address')->nullable();
                $table->string('country')->default('KH');
                $table->string('currency')->default('USD');
                $table->json('enabled_modules')->nullable();
                $table->string('logo_url')->nullable();
                $table->string('domain')->nullable();
                $table->integer('max_outlets')->default(1);
                $table->integer('max_registers')->default(2);
                $table->integer('max_users')->default(5);
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamps();
            });
        }

        // 2. Tenant Subscriptions table
        if (!Schema::hasTable('tenant_subscriptions')) {
            Schema::create('tenant_subscriptions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->string('plan_name');
                $table->enum('billing_cycle', ['monthly', 'quarterly', 'yearly'])->default('monthly');
                $table->decimal('price', 10, 2)->default(0);
                $table->string('currency')->default('USD');
                $table->enum('status', ['active', 'cancelled', 'expired', 'pending'])->default('pending');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // 3. Add tenant_id to users table
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('tenant_id')->nullable()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('tenants');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
