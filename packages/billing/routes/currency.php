<?php

use App\Domain\Currency\Http\Controllers\API\V1\CurrencyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'permission:view settings'])
    ->prefix('currencies')
    ->name('currencies.')
    ->group(function (): void {
        Route::get('/', [CurrencyController::class, 'index'])->name('index');

        Route::middleware('permission:manage settings')->group(function (): void {
            Route::put('{code}', [CurrencyController::class, 'update'])->name('update');
        });
    });
