<?php

use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ScheduleShiftController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'schedule.board'])
    ->prefix('schedules')
    ->group(function (): void {
        Route::get('/', [ScheduleController::class, 'show']);
        Route::get('/month', [ScheduleController::class, 'month']);
        Route::post('/{schedule}/shifts', [ScheduleShiftController::class, 'store']);
        Route::put('/{schedule}/shifts/{shift}', [ScheduleShiftController::class, 'update']);
        Route::delete('/{schedule}/shifts/{shift}', [ScheduleShiftController::class, 'destroy']);
        Route::post('/{schedule}/shifts/bulk', [ScheduleShiftController::class, 'bulkStore']);
        Route::post('/{schedule}/copy-from', [ScheduleController::class, 'copyFrom']);
        Route::get('/{schedule}/summary', [ScheduleController::class, 'summary']);
    });

Route::middleware(['auth:sanctum', 'manager'])
    ->prefix('schedules')
    ->group(function (): void {
        Route::post('/{schedule}/publish', [ScheduleController::class, 'publish']);
    });
