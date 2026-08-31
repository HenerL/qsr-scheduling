<?php

use App\Http\Controllers\Api\StoreController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'manager'])->prefix('store')->group(function (): void {
    Route::post('/', [StoreController::class, 'create']);
    Route::get('/', [StoreController::class, 'show']);
    Route::put('/', [StoreController::class, 'update']);

    Route::get('operating-hours', [StoreController::class, 'showHours']);
    Route::put('operating-hours', [StoreController::class, 'updateHours']);
});
