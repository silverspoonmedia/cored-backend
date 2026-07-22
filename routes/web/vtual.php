<?php

use App\Domain\Vtual\Http\Controllers\Web\VtualController;
use Illuminate\Support\Facades\Route;

Route::get('vtual', [VtualController::class, 'index'])->name('vtual.index');
Route::post('vtual/creators', [VtualController::class, 'storeCreator'])->name('vtual.creators.store');
Route::get('vtual/creators/{creator}', [VtualController::class, 'showCreator'])
    ->whereUuid('creator')
    ->name('vtual.creators.show');
Route::post('vtual/creators/{creator}/channels', [VtualController::class, 'storeChannel'])
    ->whereUuid('creator')
    ->name('vtual.creators.channels.store');
Route::post('vtual/managements', [VtualController::class, 'storeManagement'])->name('vtual.managements.store');
