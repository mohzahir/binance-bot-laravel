<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->decimal('current_price', 16, 8)->nullable();
            $table->decimal('ema_200', 16, 8)->nullable();
            $table->decimal('rsi_14', 8, 2)->nullable();
            $table->string('macd_status')->nullable(); // 'BULLISH' or 'BEARISH'
        });
    }

    public function down()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['current_price', 'ema_200', 'rsi_14', 'macd_status']);
        });
    }
};