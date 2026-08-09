<?php

use Illuminate\Support\Facades\Route;
use App\Modules\AI\Controllers\AiController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/models', [AiController::class, 'models'])->name('models');
    Route::post('/generate', [AiController::class, 'generate'])->name('generate');
    Route::get('/history', [AiController::class, 'history'])->name('history');
    Route::get('/requests/{publicId}', [AiController::class, 'show'])->name('requests.show');
});
