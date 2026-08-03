<?php

use App\Support\Settings\Http\Controllers\Web\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view settings')
    ->prefix('settings')
    ->name('settings.')
    ->group(function (): void {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::get('platform', [SettingsController::class, 'platform'])->name('platform');
        Route::get('file-hosting', [SettingsController::class, 'fileHosting'])->name('file-hosting');
        Route::get('microstock', [SettingsController::class, 'microstock'])->name('microstock');
        Route::get('vtual', [SettingsController::class, 'vtual'])->name('vtual');

        Route::middleware('permission:manage settings')->group(function (): void {
            Route::put('{group}', [SettingsController::class, 'update'])->name('update');
        });
    });
