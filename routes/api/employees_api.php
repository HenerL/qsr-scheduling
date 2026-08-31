<?php

use App\Http\Controllers\Api\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'manager'])
    ->prefix('employees')
    ->group(function (): void {
        Route::get('/', [EmployeeController::class, 'index']);
        Route::post('/', [EmployeeController::class, 'store']);
        Route::put('{id}/stations', [EmployeeController::class, 'syncStations']);
        Route::post('{id}/crew-account', [EmployeeController::class, 'provisionCrewAccount']);
        Route::put('{id}', [EmployeeController::class, 'update']);
        Route::delete('{id}', [EmployeeController::class, 'destroy']);
    });
