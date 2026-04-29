<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    // We add the new diagnostic columns to the whitelist
    protected $fillable = [
        'symbol', 
        'status', 
        'allocation_percentage',
        'current_price',
        'ema_200',
        'rsi_14',
        'macd_status'
    ];
}
