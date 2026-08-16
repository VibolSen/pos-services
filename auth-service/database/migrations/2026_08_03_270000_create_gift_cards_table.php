<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gift_cards')) {
            Schema::create('gift_cards', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('card_code')->unique();
                $table->string('customer')->default('Walk-in Customer');
                $table->decimal('balance', 12, 2)->default(0);
                $table->string('status')->default('active'); // active, redeemed, expired
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
