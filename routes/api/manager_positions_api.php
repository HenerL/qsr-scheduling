<?php

use App\Http\Controllers\Api\ManagerPositionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'manager'])
    ->prefix('manager-positions')
    ->group(function (): void {
        Route::get('/', [ManagerPositionController::class, 'index']);
        Route::post('/', [ManagerPositionController::class, 'store']);
        Route::post('defaults', [ManagerPositionController::class, 'seedDefaults']);
        Route::put('{position_id}', [ManagerPositionController::class, 'update']);
        Route::delete('{position_id}', [ManagerPositionController::class, 'destroy']);
    });
