<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transfer_number')->unique();
            $table->uuid('from_outlet_id');
            $table->uuid('to_outlet_id');
            $table->uuid('user_id');
            $table->string('status')->default('dispatched');
            $table->dateTime('dispatched_at');
            $table->dateTime('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transfer_id');
            $table->uuid('product_id');
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
    }
};
