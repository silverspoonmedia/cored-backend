<?php

use App\Domain\FileHosting\Http\Controllers\Web\FileManagerController;
use Illuminate\Support\Facades\Route;

Route::get('file-hosting', [FileManagerController::class, 'index'])->name('file-hosting.index');
Route::get('file-hosting/trash', [FileManagerController::class, 'trash'])->name('file-hosting.trash');
Route::post('file-hosting/folders', [FileManagerController::class, 'storeFolder'])->name('file-hosting.folders.store');
Route::match(['put', 'patch'], 'file-hosting/folders/{folder}/rename', [FileManagerController::class, 'renameFolder'])
    ->whereUuid('folder')
    ->name('file-hosting.folders.rename');
Route::match(['put', 'patch'], 'file-hosting/folders/{folder}/visibility', [FileManagerController::class, 'setFolderVisibility'])
    ->whereUuid('folder')
    ->name('file-hosting.folders.visibility');
Route::delete('file-hosting/folders/{folder}', [FileManagerController::class, 'destroyFolder'])
    ->whereUuid('folder')
    ->name('file-hosting.folders.destroy');
Route::get('file-hosting/files/{file}', [FileManagerController::class, 'show'])
    ->whereUuid('file')
    ->name('file-hosting.files.show');
Route::get('file-hosting/files/{file}/download', [FileManagerController::class, 'download'])
    ->whereUuid('file')
    ->name('file-hosting.files.download');
Route::match(['put', 'patch'], 'file-hosting/files/{file}/rename', [FileManagerController::class, 'renameFile'])
    ->whereUuid('file')
    ->name('file-hosting.files.rename');
Route::match(['put', 'patch'], 'file-hosting/files/{file}/visibility', [FileManagerController::class, 'setFileVisibility'])
    ->whereUuid('file')
    ->name('file-hosting.files.visibility');
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
