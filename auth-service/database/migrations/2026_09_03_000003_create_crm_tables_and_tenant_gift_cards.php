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
        // 1. Deals Pipeline Table
        if (!Schema::hasTable('deals')) {
            Schema::create('deals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id', 36)->nullable()->index();
                $table->string('title');
                $table->string('company')->nullable();
                $table->uuid('customer_id')->nullable();
                $table->decimal('value', 12, 2)->default(0);
                $table->string('stage', 50)->default('lead'); // lead, qualified, proposal, negotiation, won, lost
                $table->integer('probability')->default(50);
                $table->string('owner_name')->nullable()->default('Sales Team');
                $table->date('expected_close_date')->nullable();
                $table->timestamps();
            });
        }

        // 2. Inbound Leads Table
        if (!Schema::hasTable('leads')) {
            Schema::create('leads', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id', 36)->nullable()->index();
                $table->string('name');
                $table->string('company')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('score', 50)->default('Warm (70)');
                $table->string('source', 50)->default('Web Form');
                $table->string('status', 50)->default('new'); // new, contacted, qualified, lost
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 3. Customer Touchpoints & Activity Logs
        if (!Schema::hasTable('crm_activities')) {
            Schema::create('crm_activities', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id', 36)->nullable()->index();
                $table->uuid('deal_id')->nullable();
                $table->uuid('customer_id')->nullable();
                $table->string('type', 50)->default('call'); // call, email, meeting, note
                $table->string('title');
                $table->string('contact')->nullable();
                $table->text('summary')->nullable();
                $table->timestamps();
            });
        }

        // 4. Multi-Tenant Scoping for Gift Cards
        if (Schema::hasTable('gift_cards') && !Schema::hasColumn('gift_cards', 'tenant_id')) {
            Schema::table('gift_cards', function (Blueprint $table) {
                $table->string('tenant_id', 36)->nullable()->index()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('deals');

        if (Schema::hasTable('gift_cards') && Schema::hasColumn('gift_cards', 'tenant_id')) {
            Schema::table('gift_cards', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
