<?php

use App\Http\Controllers\Api\ShiftTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'manager'])
    ->prefix('shift-templates')
    ->group(function (): void {
        Route::get('/', [ShiftTemplateController::class, 'index']);
        Route::post('/', [ShiftTemplateController::class, 'store']);
        Route::post('defaults', [ShiftTemplateController::class, 'seedDefaults']);
        Route::put('{template_id}', [ShiftTemplateController::class, 'update']);
        Route::delete('{template_id}', [ShiftTemplateController::class, 'destroy']);
    });
