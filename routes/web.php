<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BotDashboardController;

// 1. Listen for traffic SPECIFICALLY on the subdomain
Route::domain('bot.hirehub-sd.com')->group(function () {
    
    // 2. Protect the route with basic authentication
    Route::middleware('auth.basic')->group(function () {
        
        Route::get('/', [BotDashboardController::class, 'index']);
        
    });
});