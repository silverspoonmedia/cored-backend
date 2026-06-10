<?php

use App\Http\Controllers\Web\Auth\EmailVerificationController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\PasswordController;
use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Navigation\MenuController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web (Blade) frontend
|--------------------------------------------------------------------------
| Authentication is the same stateless JWT as the API, carried in an HttpOnly
| cookie. `AuthenticateWithJwtCookie` (appended to the web group) populates the
| `api` guard from that cookie, so `web.auth`, `web.guest`, `permission:` and
| `UserPolicy` all reuse the existing backend with no duplication.
*/

Route::get('/', fn () => redirect()->route(Auth::guard('api')->check() ? 'dashboard' : 'login'));

// Guest-only auth screens.
Route::middleware('web.guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');

    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('forgot-password', [PasswordResetController::class, 'showLinkRequest'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');

    Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

// Authenticated area.
Route::middleware('web.auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Self-service profile.
    Route::get('profile/password', [PasswordController::class, 'edit'])->name('profile.password.edit');
    Route::put('profile/password', [PasswordController::class, 'update'])->name('profile.password.update');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->name('verification.send');

    // User management (H-RBAC). Permission gates mirror the API surface.
    Route::get('users', [UserController::class, 'index'])
        ->middleware('permission:view user')->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])
        ->middleware('permission:create user')->name('users.create');
    Route::post('users', [UserController::class, 'store'])
        ->middleware('permission:create user')->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('permission:edit user')->whereUuid('user')->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])
        ->middleware('permission:edit user')->whereUuid('user')->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:delete user')->whereUuid('user')->name('users.destroy');

    // Navigation menu management.
    Route::get('menus', [MenuController::class, 'index'])
        ->middleware('permission:view menu')->name('menus.index');
    Route::get('menus/trashed', [MenuController::class, 'trashed'])
        ->middleware('permission:view menu')->name('menus.trashed');
    Route::get('menus/create', [MenuController::class, 'create'])
        ->middleware('permission:manage menu')->name('menus.create');
    Route::post('menus', [MenuController::class, 'store'])
        ->middleware('permission:manage menu')->name('menus.store');
    Route::get('menus/{menu}/edit', [MenuController::class, 'edit'])
        ->middleware('permission:manage menu')->whereUuid('menu')->name('menus.edit');
    Route::put('menus/{menu}', [MenuController::class, 'update'])
        ->middleware('permission:manage menu')->whereUuid('menu')->name('menus.update');
    Route::delete('menus/{menu}', [MenuController::class, 'destroy'])
        ->middleware('permission:manage menu')->whereUuid('menu')->name('menus.destroy');
    Route::post('menus/{menu}/restore', [MenuController::class, 'restore'])
        ->middleware('permission:restore menu')->whereUuid('menu')->withTrashed()->name('menus.restore');
});
