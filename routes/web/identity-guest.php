<?php

use App\Domain\Identity\Http\Controllers\Web\Auth\LoginController;
use App\Domain\Identity\Http\Controllers\Web\Auth\PasswordResetController;
use App\Domain\Identity\Http\Controllers\Web\Auth\RegisterController;
use App\Domain\Identity\Http\Controllers\Web\Auth\TwoFactorController as TwoFactorWebController;
use Illuminate\Support\Facades\Route;

Route::get('login', [LoginController::class, 'create'])->name('login');
Route::post('login', [LoginController::class, 'store'])->name('login.store');

Route::get('two-factor-challenge', [TwoFactorWebController::class, 'create'])->name('two-factor.challenge');
Route::post('two-factor-challenge', [TwoFactorWebController::class, 'store'])->name('two-factor.challenge.store');

Route::get('register', [RegisterController::class, 'create'])->name('register');
Route::post('register', [RegisterController::class, 'store'])->name('register.store');

Route::get('forgot-password', [PasswordResetController::class, 'showLinkRequest'])->name('password.request');
Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');

Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
