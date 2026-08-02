<?php

use App\Domain\Service\Http\Controllers\Web\CurrencyController;
use App\Domain\Service\Http\Controllers\Web\ServiceController;
use App\Domain\Service\Http\Controllers\Web\ServicePurchaseController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view settings')
    ->prefix('currencies')
    ->name('currencies.')
    ->group(function (): void {
        Route::get('/', [CurrencyController::class, 'index'])->name('index');

        Route::middleware('permission:manage settings')->group(function (): void {
            Route::put('{code}', [CurrencyController::class, 'update'])->name('update');
        });
    });

Route::get('services', [ServiceController::class, 'index'])->name('services.index');
Route::get('services/create', [ServiceController::class, 'create'])->name('services.create');
Route::post('services', [ServiceController::class, 'store'])->name('services.store');
Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show')->whereUuid('service');
Route::get('services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit')->whereUuid('service');
Route::put('services/{service}', [ServiceController::class, 'update'])->name('services.update')->whereUuid('service');
Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy')->whereUuid('service');
Route::post('services/{service}/checkout', [ServiceController::class, 'checkout'])->name('services.checkout')->whereUuid('service');

Route::get('service-purchases/{purchase}', [ServicePurchaseController::class, 'show'])
    ->name('service-purchases.show')
    ->whereUuid('purchase');
Route::patch('service-purchases/{purchase}/quantity', [ServicePurchaseController::class, 'updateQuantity'])
    ->name('service-purchases.update-quantity')
    ->whereUuid('purchase');
