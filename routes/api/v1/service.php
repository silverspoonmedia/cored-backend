<?php

use App\Domain\Service\Http\Controllers\API\V1\CurrencyController;
use App\Domain\Service\Http\Controllers\API\V1\ServiceController;
use App\Domain\Service\Http\Controllers\API\V1\ServicePurchaseController;
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

Route::middleware('auth:api')->group(function (): void {
    Route::get('services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('services', [ServiceController::class, 'store'])->name('services.store');
    Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show')->whereUuid('service');
    Route::put('services/{service}', [ServiceController::class, 'update'])->name('services.update')->whereUuid('service');
    Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy')->whereUuid('service');
    Route::post('services/{service}/checkout', [ServiceController::class, 'checkout'])->name('services.checkout')->whereUuid('service');

    Route::get('service-purchases/{purchase}', [ServicePurchaseController::class, 'show'])
        ->name('service-purchases.show')
        ->whereUuid('purchase');
    Route::patch('service-purchases/{purchase}/quantity', [ServicePurchaseController::class, 'updateQuantity'])
        ->name('service-purchases.update-quantity')
        ->whereUuid('purchase');
});
