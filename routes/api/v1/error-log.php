<?php

use App\Domain\ErrorLog\Http\Controllers\API\V1\ErrorLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'permission:view error log'])
    ->prefix('error-logs')
    ->name('error-logs.')
    ->group(function (): void {
        Route::get('/', [ErrorLogController::class, 'index'])->name('index');
        Route::get('{errorLog}', [ErrorLogController::class, 'show'])
            ->whereUuid('errorLog')
            ->name('show');
        Route::patch('{errorLog}/status', [ErrorLogController::class, 'updateStatus'])
            ->whereUuid('errorLog')
            ->name('status');
    });
