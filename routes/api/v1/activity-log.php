<?php

use App\Domain\ActivityLog\Http\Controllers\API\V1\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'permission:view activity log'])
    ->prefix('activity-logs')
    ->name('activity-logs.')
    ->group(function (): void {
        Route::get('/', [ActivityLogController::class, 'index'])->name('index');
        Route::get('{activity}', [ActivityLogController::class, 'show'])
            ->whereNumber('activity')
            ->name('show');
    });

Route::middleware(['auth:api', 'permission:view activity log'])
    ->get('users/{user}/activity-logs', [ActivityLogController::class, 'forUser'])
    ->whereUuid('user')
    ->name('users.activity-logs');
