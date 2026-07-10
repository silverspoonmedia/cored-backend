<?php

use App\Domain\Playground\Http\Controllers\Web\OrderAttachmentUploadController;
use App\Domain\Playground\Http\Controllers\Web\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('orders/datatable', [OrderController::class, 'datatable'])->name('orders.datatable');
Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->whereUuid('order')->name('orders.edit');
Route::prefix('orders/attachments/uploads')->name('orders.attachments.uploads.')->group(function () {
    Route::post('/', [OrderAttachmentUploadController::class, 'store'])->name('store');
    Route::get('{upload}', [OrderAttachmentUploadController::class, 'show'])->name('show')->whereUuid('upload');
    Route::post('{upload}/parts', [OrderAttachmentUploadController::class, 'signParts'])->name('sign-parts')->whereUuid('upload');
    Route::post('{upload}/complete', [OrderAttachmentUploadController::class, 'complete'])->name('complete')->whereUuid('upload');
    Route::delete('{upload}', [OrderAttachmentUploadController::class, 'destroy'])->name('destroy')->whereUuid('upload');
});
Route::delete('orders/bulk', [OrderController::class, 'bulkDestroy'])->name('orders.bulk-destroy');
Route::apiResource('orders', OrderController::class)->except(['create', 'edit'])->whereUuid('order');
