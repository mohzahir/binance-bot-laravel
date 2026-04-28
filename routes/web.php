<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

use App\Http\Controllers\BotDashboardController;

// 1. Listen for traffic SPECIFICALLY on the subdomain
Route::domain('bot.hirehub-sd.com')->group(function () {
    
    // 2. Protect the route with basic authentication
    Route::middleware('auth.basic')->group(function () {
        
        Route::get('/', [BotDashboardController::class, 'index']);
        
    });
});

Route::get('/fix-admin', function () {
    // Find the user you created in phpMyAdmin
    $user = User::where('email', 'admin@hirehub-sd.com')->first();
    
    if ($user) {
        // Force Laravel to hash the password using your server's APP_KEY
        $user->password = Hash::make('12345678');
        $user->save();
        return "SUCCESS: Password correctly hashed and updated in the database!";
    }
    
    return "ERROR: User not found. Check the email address.";
});