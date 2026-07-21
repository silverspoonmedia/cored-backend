<?php

use App\Support\Product\Http\Controllers\Web\ProductActivationController;
use Illuminate\Support\Facades\Route;

Route::prefix('product-activations')->name('product-activations.')->group(function (): void {
    Route::get('/', [ProductActivationController::class, 'index'])->name('index');
    Route::get('{productKey}', [ProductActivationController::class, 'show'])->name('show');

    Route::middleware('permission:manage product activations')->group(function (): void {
        Route::post('{productKey}/activations/{id}/suspend', [ProductActivationController::class, 'suspend'])->name('suspend');
        Route::post('{productKey}/activations/{id}/reactivate', [ProductActivationController::class, 'reactivate'])->name('reactivate');
        Route::post('{productKey}/activations/{id}/approve', [ProductActivationController::class, 'approve'])->name('approve');
        Route::post('{productKey}/activations/{id}/reject', [ProductActivationController::class, 'reject'])->name('reject');
    });
});
