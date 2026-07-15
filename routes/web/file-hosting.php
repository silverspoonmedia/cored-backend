<?php

use App\Domain\FileHosting\Http\Controllers\Web\FileManagerController;
use Illuminate\Support\Facades\Route;

Route::get('file-hosting', [FileManagerController::class, 'index'])->name('file-hosting.index');
Route::get('file-hosting/trash', [FileManagerController::class, 'trash'])->name('file-hosting.trash');
Route::post('file-hosting/folders', [FileManagerController::class, 'storeFolder'])->name('file-hosting.folders.store');
Route::delete('file-hosting/folders/{folder}', [FileManagerController::class, 'destroyFolder'])
    ->whereUuid('folder')
    ->name('file-hosting.folders.destroy');
Route::delete('file-hosting/files/{file}', [FileManagerController::class, 'destroyFile'])
    ->whereUuid('file')
    ->name('file-hosting.files.destroy');

Route::post('file-hosting/uploads', [FileManagerController::class, 'initUpload'])->name('file-hosting.uploads.store');
Route::get('file-hosting/uploads/{upload}', [FileManagerController::class, 'uploadStatus'])
    ->whereUuid('upload')
    ->name('file-hosting.uploads.show');
Route::post('file-hosting/uploads/{upload}/sign-parts', [FileManagerController::class, 'signParts'])
    ->whereUuid('upload')
    ->name('file-hosting.uploads.sign-parts');
Route::post('file-hosting/uploads/{upload}/complete', [FileManagerController::class, 'completeUpload'])
    ->whereUuid('upload')
    ->name('file-hosting.uploads.complete');
Route::delete('file-hosting/uploads/{upload}', [FileManagerController::class, 'abortUpload'])
    ->whereUuid('upload')
    ->name('file-hosting.uploads.destroy');
