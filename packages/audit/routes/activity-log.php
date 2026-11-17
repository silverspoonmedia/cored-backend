<?php

use App\Domain\ActivityLog\Http\Controllers\API\V1\ActivityLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ActivityLog API routes — owned by `silverspoon/audit`.
|--------------------------------------------------------------------------
|
| No prefix/name wrapper here: the host applies the versioned layer, so route
| names stay `activity-logs.*` exactly as when this file lived in
| routes/api/v1/. One package may own several route files (ActivityLog and
| ErrorLog) — the registry maps each domain to its own.
|
*/

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
