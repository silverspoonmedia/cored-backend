<?php

use App\Domain\Service\Http\Controllers\Web\ServiceController;
use App\Domain\Service\Http\Controllers\Web\ServicePurchaseController;
use Illuminate\Support\Facades\Route;

Route::get('services', [ServiceController::class, 'index'])->name('services.index');
Route::get('services/create', [ServiceController::class, 'create'])->name('services.create');
Route::post('services', [ServiceController::class, 'store'])->name('services.store');
Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show')->whereUuid('service');
Route::get('services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit')->whereUuid('service');
Route::put('services/{service}', [ServiceController::class, 'update'])->name('services.update')->whereUuid('service');
Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy')->whereUuid('service');
Route::post('services/{service}/checkout', [ServiceController::class, 'checkout'])->name('services.checkout')->whereUuid('service');

Route::get('service-purchases/{purchase}', [ServicePurchaseController::class, 'show'])
    ->name('service-purchases.show')
    ->whereUuid('purchase');
