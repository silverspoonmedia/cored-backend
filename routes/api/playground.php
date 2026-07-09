<?php

use App\Domain\Playground\Http\Controllers\API\PlaygroundAttachmentUploadController;
use App\Domain\Playground\Http\Controllers\API\PlaygroundController;
use App\Domain\Playground\Http\Controllers\API\PlaygroundOrderController;
use Illuminate\Support\Facades\Route;

if (! app()->environment('production')) {
    Route::prefix('playground')->group(function (): void {
        Route::apiResource('users', PlaygroundController::class)
            ->except(['create', 'edit'])
            ->names('playground.users');

        Route::delete('orders/bulk', [PlaygroundOrderController::class, 'bulkDestroy'])
            ->name('playground.orders.bulk-destroy');

        Route::prefix('attachments/uploads')->name('playground.attachments.uploads.')->group(function (): void {
            Route::post('/', [PlaygroundAttachmentUploadController::class, 'store'])->name('store');
            Route::get('{upload}', [PlaygroundAttachmentUploadController::class, 'show'])->name('show')->whereUuid('upload');
            Route::post('{upload}/parts', [PlaygroundAttachmentUploadController::class, 'signParts'])->name('sign-parts')->whereUuid('upload');
            Route::post('{upload}/complete', [PlaygroundAttachmentUploadController::class, 'complete'])->name('complete')->whereUuid('upload');
            Route::delete('{upload}', [PlaygroundAttachmentUploadController::class, 'destroy'])->name('destroy')->whereUuid('upload');
        });

        Route::apiResource('orders', PlaygroundOrderController::class)
            ->except(['create', 'edit'])
            ->names('playground.orders');
    });
}
