<?php

use App\Http\Controllers\Api\CrewStationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'manager'])
    ->prefix('crew-stations')
    ->group(function (): void {
        Route::get('/', [CrewStationController::class, 'index']);
        Route::post('/', [CrewStationController::class, 'store']);
        Route::post('defaults', [CrewStationController::class, 'seedDefaults']);
        Route::put('{station_id}', [CrewStationController::class, 'update']);
        Route::delete('{station_id}', [CrewStationController::class, 'destroy']);
    });
