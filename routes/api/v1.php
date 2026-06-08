<?php

use App\Http\Controllers\API\V1\AdminController;
use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\Auth\EmailVerificationController;
use App\Http\Controllers\API\V1\Auth\PasswordController;
use App\Http\Controllers\API\V1\Navigation\MenuController;
use App\Http\Controllers\API\V1\PlaygroundController;
use App\Http\Controllers\API\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::get('ping', [PlaygroundController::class, 'ping']);

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('forgot-password', [PasswordController::class, 'forgot'])->name('password.forgot');
    Route::post('reset-password', [PasswordController::class, 'reset'])->name('password.reset');

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
    });

    Route::post('refresh', [AuthController::class, 'refresh'])
        ->middleware('jwt.refresh')
        ->name('refresh');
});

Route::middleware(['auth:api', 'permission:view user'])
    ->get('admin/ping', [AdminController::class, 'ping'])
    ->name('admin.ping');

Route::middleware('auth:api')->group(function (): void {
    Route::get('users/assignable-roles', [UserController::class, 'assignableRoles'])
        ->name('users.assignable-roles');

    Route::apiResource('users', UserController::class);
});

Route::prefix('navigation/menus')->name('navigation.menus.')->group(function (): void {
    // Public/optional-auth navigation tree: filtered for the current viewer.
    Route::middleware('jwt.optional')->get('/', [MenuController::class, 'index'])->name('index');

    Route::middleware('auth:api')->group(function (): void {
        // Read-only management views.
        Route::middleware('permission:view menu')->group(function (): void {
            Route::get('list', [MenuController::class, 'list'])->name('list');
            Route::get('{menu}', [MenuController::class, 'show'])->whereUuid('menu')->name('show');
        });

        // Mutating management endpoints.
        Route::middleware('permission:manage menu')->group(function (): void {
            Route::post('/', [MenuController::class, 'store'])->name('store');
            Route::match(['put', 'patch'], '{menu}', [MenuController::class, 'update'])->whereUuid('menu')->name('update');
            Route::delete('{menu}', [MenuController::class, 'destroy'])->whereUuid('menu')->name('destroy');
        });
    });
});
