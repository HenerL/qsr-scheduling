<?php

use App\Http\Controllers\Api\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->prefix('crew')
    ->group(function (): void {
        Route::get('/my-schedule', [ScheduleController::class, 'crewMySchedule']);
    });
