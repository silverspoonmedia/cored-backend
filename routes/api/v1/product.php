<?php

use App\Domain\Product\Http\Controllers\API\V1\ItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('product')->name('product.')->group(function (): void {
    Route::middleware('permission:view product item')->group(function (): void {
        Route::get('items', [ItemController::class, 'index'])->name('items.index');
        Route::get('items/{item}', [ItemController::class, 'show'])->whereUuid('item')->name('items.show');
    });

    Route::middleware('permission:create product item')->group(function (): void {
        Route::post('items', [ItemController::class, 'store'])->name('items.store');
    });

    Route::middleware('permission:edit product item')->group(function (): void {
        Route::match(['put', 'patch'], 'items/{item}', [ItemController::class, 'update'])
            ->whereUuid('item')
            ->name('items.update');
    });

    Route::middleware('permission:delete product item')->group(function (): void {
        Route::delete('items/{item}', [ItemController::class, 'destroy'])
            ->whereUuid('item')
            ->name('items.destroy');
    });
});
