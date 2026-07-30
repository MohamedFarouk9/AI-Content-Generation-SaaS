<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Identity\Controllers\AuthController;
use App\Modules\Identity\Controllers\OAuthController;

Route::middleware('web')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest')->name('login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('guest')->name('register');
    
    // OAuth Routes
    Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])->middleware('guest')->name('oauth.redirect');
    Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->middleware('guest')->name('oauth.callback');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', [AuthController::class, 'user'])->name('user');
});
