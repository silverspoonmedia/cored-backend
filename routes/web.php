<?php

use App\Http\Controllers\Web\Auth\EmailVerificationController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\PasswordController;
use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\Navigation\MenuController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web (Blade) frontend
|--------------------------------------------------------------------------
| The Blade frontend is a pure CONSUMER of the versioned API: every controller
| forwards to `/api/v1/*` (see App\Support\Api\ApiClient) and only renders the
| JSON it gets back. Business logic — validation, CRUD, RBAC — lives solely in
| the API, so these routes carry NO `permission:` gate: the API authorizes each
| forwarded call. Web middleware here is transport-only: `web.guest`/`web.auth`
| (who is logged in, where to redirect) and the JWT-cookie bridge that lets the
| browser carry the same stateless token the API issues.
*/

Route::get('/', fn () => redirect()->route(Auth::guard('api')->check() ? 'dashboard' : 'login'));

Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

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

    // Sidebar navigation tree (optional ?q= filter) — proxied to the API.
    Route::get('navigation/tree', [MenuController::class, 'navigationTree'])->name('navigation.tree');

    // Self-service profile.
    Route::get('profile/password', [PasswordController::class, 'edit'])->name('profile.password.edit');
    Route::put('profile/password', [PasswordController::class, 'update'])->name('profile.password.update');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->name('verification.send');

    // User management — forwarded to /api/v1/users (the API authorizes).
    Route::get('users/datatable', [UserController::class, 'datatable'])->name('users.datatable');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->whereUuid('user')->name('users.edit');
    Route::apiResource('users', UserController::class)->except(['show'])->whereUuid('user');

    // Navigation menu management — forwarded to /api/v1/navigation/menus.
    Route::get('menus/trashed', [MenuController::class, 'trashed'])->name('menus.trashed');
    Route::get('menus/create', [MenuController::class, 'create'])->name('menus.create');
    Route::get('menus/{menu}/edit', [MenuController::class, 'edit'])->whereUuid('menu')->name('menus.edit');
    Route::post('menus/{menu}/restore', [MenuController::class, 'restore'])->whereUuid('menu')->name('menus.restore');
    Route::apiResource('menus', MenuController::class)->except(['show'])->whereUuid('menu');

    // Activity notifications — forwarded to /api/v1/notifications (the API authorizes).
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->whereUuid('notification')
        ->name('notifications.read');
});
