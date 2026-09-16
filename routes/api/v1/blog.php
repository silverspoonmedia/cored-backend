<?php

use App\Domain\Blog\Http\Controllers\API\V1\BlogMediaController;
use App\Domain\Blog\Http\Controllers\API\V1\CategoryController;
use App\Domain\Blog\Http\Controllers\API\V1\PostController;
use App\Domain\Blog\Http\Controllers\API\V1\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('blog/posts')->name('blog.posts.')->middleware('auth:api')->group(function (): void {
    Route::middleware('permission:view posts')->group(function (): void {
        Route::get('/', [PostController::class, 'index'])->name('index');
        Route::get('statuses', [PostController::class, 'statuses'])->name('statuses');
        Route::get('trashed', [PostController::class, 'trashed'])->name('trashed');
        Route::get('{post}', [PostController::class, 'show'])
            ->whereUuid('post')
            ->withTrashed()
            ->name('show');
    });

    Route::middleware('permission:manage posts')->group(function (): void {
        Route::post('/', [PostController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{post}', [PostController::class, 'update'])
            ->whereUuid('post')
            ->name('update');
        Route::delete('{post}', [PostController::class, 'destroy'])->whereUuid('post')->name('destroy');
    });

    Route::middleware('permission:restore posts')
        ->post('{post}/restore', [PostController::class, 'restore'])
        ->whereUuid('post')
        ->withTrashed()
        ->name('restore');
});

Route::prefix('blog/categories')->name('blog.categories.')->middleware('auth:api')->group(function (): void {
    Route::middleware('permission:view posts')->group(function (): void {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::get('{category}', [CategoryController::class, 'show'])->whereUuid('category')->name('show');
    });

    Route::middleware('permission:manage posts')->group(function (): void {
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{category}', [CategoryController::class, 'update'])
            ->whereUuid('category')
            ->name('update');
        Route::delete('{category}', [CategoryController::class, 'destroy'])->whereUuid('category')->name('destroy');
    });
});

Route::prefix('blog/tags')->name('blog.tags.')->middleware('auth:api')->group(function (): void {
    Route::middleware('permission:view posts')->group(function (): void {
        Route::get('/', [TagController::class, 'index'])->name('index');
        Route::get('{tag}', [TagController::class, 'show'])->whereUuid('tag')->name('show');
    });

    Route::middleware('permission:manage posts')->group(function (): void {
        Route::post('/', [TagController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{tag}', [TagController::class, 'update'])
            ->whereUuid('tag')
            ->name('update');
        Route::delete('{tag}', [TagController::class, 'destroy'])->whereUuid('tag')->name('destroy');
    });
});

Route::prefix('blog/media')->name('blog.media.')->middleware('auth:api')->group(function (): void {
    Route::middleware('permission:manage posts')->group(function (): void {
        Route::post('/', [BlogMediaController::class, 'store'])->name('store');
        Route::delete('{media}', [BlogMediaController::class, 'destroy'])->whereUuid('media')->name('destroy');
    });
});
