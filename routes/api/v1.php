<?php

use App\Http\Controllers\API\V1\ActivityLogController;
use App\Http\Controllers\API\V1\AdminController;
use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\Auth\EmailVerificationController;
use App\Http\Controllers\API\V1\Auth\PasswordController;
use App\Http\Controllers\API\V1\Auth\TwoFactorController;
use App\Http\Controllers\API\V1\Navigation\MenuController;
use App\Http\Controllers\API\V1\NotificationController;
use App\Http\Controllers\API\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('register');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login');
    Route::post('two-factor/login', [TwoFactorController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('two-factor.login');
    Route::post('forgot-password', [PasswordController::class, 'forgot'])
        ->middleware('throttle:3,1')
        ->name('password.forgot');
    Route::post('reset-password', [PasswordController::class, 'reset'])
        ->middleware('throttle:3,1')
        ->name('password.reset');

    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware('auth:api')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
        Route::post('two-factor/setup', [TwoFactorController::class, 'startSetup'])
            ->middleware('throttle:3,1')
            ->name('two-factor.setup');
        Route::post('two-factor/confirm', [TwoFactorController::class, 'confirmSetup'])
            ->middleware('throttle:3,1')
            ->name('two-factor.confirm');
        Route::post('two-factor/disable', [TwoFactorController::class, 'disable'])
            ->middleware('throttle:3,1')
            ->name('two-factor.disable');
    });

    Route::post('refresh', [AuthController::class, 'refresh'])
        ->middleware('jwt.refresh')
        ->name('refresh');
});

Route::middleware('auth:api')->group(function (): void {
    Route::get('users/assignable-roles', [UserController::class, 'assignableRoles'])
        ->name('users.assignable-roles');

    Route::apiResource('users', UserController::class);
});

Route::middleware(['auth:api', 'permission:view user'])
    ->get('admin/ping', [AdminController::class, 'ping'])
    ->name('admin.ping');

// Role-targeted activity notifications. `view notification` gates the surface;
// per-row role overlap is enforced by the service/repository.
Route::middleware(['auth:api', 'permission:view notification'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])
            ->whereUuid('notification')
            ->name('read');
    });

// Immutable audit trail (Spatie activitylog). Distinct from activity notifications.
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

Route::prefix('navigation/menus')->name('navigation.menus.')->group(function (): void {
    // Public/optional-auth navigation tree: filtered for the current viewer.
    Route::middleware('jwt.optional')->get('/', [MenuController::class, 'index'])->name('index');

    Route::middleware('auth:api')->group(function (): void {
        // Read-only management views.
        Route::middleware('permission:view menu')->group(function (): void {
            Route::get('list', [MenuController::class, 'list'])->name('list');
            Route::get('trashed', [MenuController::class, 'trashed'])->name('trashed');
            Route::get('{menu}', [MenuController::class, 'show'])->whereUuid('menu')->name('show');
        });

        // Mutating management endpoints.
        Route::middleware('permission:manage menu')->group(function (): void {
            Route::post('/', [MenuController::class, 'store'])->name('store');
            Route::match(['put', 'patch'], '{menu}', [MenuController::class, 'update'])->whereUuid('menu')->name('update');
            Route::delete('{menu}', [MenuController::class, 'destroy'])->whereUuid('menu')->name('destroy');
        });

        // Recovery of soft-deleted menus (binds the trashed record).
        Route::middleware('permission:restore menu')
            ->post('{menu}/restore', [MenuController::class, 'restore'])
            ->whereUuid('menu')
            ->withTrashed()
            ->name('restore');
    });
});
