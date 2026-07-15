<?php

use App\Domain\FileHosting\Http\Controllers\API\V1\BrowseController;
use App\Domain\FileHosting\Http\Controllers\API\V1\FileController;
use App\Domain\FileHosting\Http\Controllers\API\V1\FolderController;
use App\Domain\FileHosting\Http\Controllers\API\V1\QuotaController;
use App\Domain\FileHosting\Http\Controllers\API\V1\TrashController;
use App\Domain\FileHosting\Http\Controllers\API\V1\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('file-hosting')->name('file-hosting.')->group(function (): void {
    Route::middleware('permission:view file hosting folder|view file hosting file')->group(function (): void {
        Route::get('browse', BrowseController::class)->name('browse');
        Route::get('trash', TrashController::class)->name('trash');
        Route::get('quota', QuotaController::class)->name('quota');
        Route::get('search', [FileController::class, 'search'])->name('search');
        Route::get('files/{file}', [FileController::class, 'show'])->whereUuid('file')->name('files.show');
        Route::get('files/{file}/download', [FileController::class, 'download'])->whereUuid('file')->name('files.download');
    });

    Route::middleware('permission:create file hosting folder')->group(function (): void {
        Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
    });

    Route::middleware('permission:edit file hosting folder')->group(function (): void {
        Route::patch('folders/{folder}/rename', [FolderController::class, 'rename'])->whereUuid('folder')->name('folders.rename');
        Route::patch('folders/{folder}/move', [FolderController::class, 'move'])->whereUuid('folder')->name('folders.move');
        Route::post('folders/{folder}/restore', [FolderController::class, 'restore'])->whereUuid('folder')->name('folders.restore');
    });

    Route::middleware('permission:delete file hosting folder')->group(function (): void {
        Route::delete('folders/{folder}', [FolderController::class, 'destroy'])->whereUuid('folder')->name('folders.destroy');
        Route::delete('folders/{folder}/force', [FolderController::class, 'forceDestroy'])->whereUuid('folder')->name('folders.force-destroy');
    });

    Route::middleware('permission:edit file hosting file')->group(function (): void {
        Route::patch('files/{file}/rename', [FileController::class, 'rename'])->whereUuid('file')->name('files.rename');
        Route::patch('files/{file}/move', [FileController::class, 'move'])->whereUuid('file')->name('files.move');
        Route::post('files/{file}/restore', [FileController::class, 'restore'])->whereUuid('file')->name('files.restore');
    });

    Route::middleware('permission:delete file hosting file')->group(function (): void {
        Route::delete('files/{file}', [FileController::class, 'destroy'])->whereUuid('file')->name('files.destroy');
        Route::delete('files/{file}/force', [FileController::class, 'forceDestroy'])->whereUuid('file')->name('files.force-destroy');
    });

    Route::middleware('permission:create file hosting file')->group(function (): void {
        Route::post('uploads', [UploadController::class, 'store'])->name('uploads.store');
        Route::get('uploads/{upload}', [UploadController::class, 'show'])->whereUuid('upload')->name('uploads.show');
        Route::post('uploads/{upload}/sign-parts', [UploadController::class, 'signParts'])->whereUuid('upload')->name('uploads.sign-parts');
        Route::post('uploads/{upload}/complete', [UploadController::class, 'complete'])->whereUuid('upload')->name('uploads.complete');
        Route::delete('uploads/{upload}', [UploadController::class, 'destroy'])->whereUuid('upload')->name('uploads.destroy');
    });
});
