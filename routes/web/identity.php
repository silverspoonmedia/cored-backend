<?php

use App\Domain\Identity\Http\Controllers\Web\Auth\EmailVerificationController;
use App\Domain\Identity\Http\Controllers\Web\Auth\LoginController;
use App\Domain\Identity\Http\Controllers\Web\Auth\PasswordController;
use App\Domain\Identity\Http\Controllers\Web\Auth\ProfileTwoFactorController;
use App\Domain\Identity\Http\Controllers\Web\DashboardController;
use App\Domain\Identity\Http\Controllers\Web\LookupController;
use App\Domain\Identity\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('profile/password', [PasswordController::class, 'edit'])->name('profile.password.edit');
Route::put('profile/password', [PasswordController::class, 'update'])->name('profile.password.update');
Route::get('profile/two-factor', [ProfileTwoFactorController::class, 'edit'])->name('profile.two-factor.edit');
Route::post('profile/two-factor/setup', [ProfileTwoFactorController::class, 'setup'])->name('profile.two-factor.setup');
Route::post('profile/two-factor/confirm', [ProfileTwoFactorController::class, 'confirm'])->name('profile.two-factor.confirm');
Route::post('profile/two-factor/disable', [ProfileTwoFactorController::class, 'disable'])->name('profile.two-factor.disable');
Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->name('verification.send');

Route::prefix('lookups')->name('lookups.')->group(function (): void {
    Route::get('users', [LookupController::class, 'users'])->name('users');
    Route::get('menus', [LookupController::class, 'menus'])->name('menus');
    Route::get('roles', [LookupController::class, 'roles'])->name('roles');
});

Route::get('users/datatable', [UserController::class, 'datatable'])->name('users.datatable');
Route::get('users/create', [UserController::class, 'create'])->name('users.create');
Route::get('users/{user}/edit', [UserController::class, 'edit'])->whereUuid('user')->name('users.edit');
Route::apiResource('users', UserController::class)->except(['show'])->whereUuid('user');
