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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('symbol'); // e.g., 'SOLUSDT'
            $table->string('type'); // 'BUY' or 'SELL'
            
            // Using 20 total digits, with 8 after the decimal point for crypto precision
            $table->decimal('entry_price', 20, 8); 
            $table->decimal('exit_price', 20, 8)->nullable(); // Null when trade is ongoing
            $table->decimal('quantity', 20, 8); 
            $table->decimal('stop_loss', 20, 8)->nullable();
            $table->decimal('take_profit', 20, 8)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
