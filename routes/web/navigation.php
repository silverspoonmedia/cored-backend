<?php

use App\Domain\Navigation\Http\Controllers\Web\MenuController;
use Illuminate\Support\Facades\Route;

Route::get('navigation/tree', [MenuController::class, 'navigationTree'])->name('navigation.tree');

Route::get('menus/trashed', [MenuController::class, 'trashed'])->name('menus.trashed');
Route::get('menus/create', [MenuController::class, 'create'])->name('menus.create');
Route::get('menus/{menu}/edit', [MenuController::class, 'edit'])->whereUuid('menu')->name('menus.edit');
Route::post('menus/{menu}/restore', [MenuController::class, 'restore'])->whereUuid('menu')->name('menus.restore');
Route::apiResource('menus', MenuController::class)->except(['show'])->whereUuid('menu');
