<?php

use Illuminate\Support\Facades\Route;
// use Illuminate\Support\Facades\Hash;
// use App\Models\User;

use App\Http\Controllers\BotDashboardController;

// 1. Listen for traffic SPECIFICALLY on the subdomain
Route::domain('bot.hirehub-sd.com')->group(function () {
    
    // 2. Protect the route with basic authentication
    Route::middleware('auth.basic')->group(function () {
        
        Route::get('/', [BotDashboardController::class, 'index']);
        
    });
});

// Route::get('/fix-admin', function () {
//     // This will update the user if found, or create a brand new one if missing
//     $user = User::updateOrCreate(
//         ['email' => 'admin@hirehub-sd.com'], // Search criteria
//         [
//             'name' => 'Zahir Admin',
//             'password' => Hash::make('12345678')
//         ] // Data to update/create
//     );
    
//     return "SUCCESS: User " . $user->email . " is now securely registered and hashed!";
// });