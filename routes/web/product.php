<?php

use App\Domain\Product\Http\Controllers\Web\ItemController;
use Illuminate\Support\Facades\Route;

Route::get('product-items/create', [ItemController::class, 'create'])->name('product-items.create');
Route::get('product-items/{item}/edit', [ItemController::class, 'edit'])->whereUuid('item')->name('product-items.edit');
Route::apiResource('product-items', ItemController::class)
    ->except(['show', 'create', 'edit'])
    ->whereUuid('item');
