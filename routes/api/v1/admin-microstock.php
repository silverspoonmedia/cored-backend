<?php

use App\Domain\Microstock\Http\Controllers\API\V1\AdminBillingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])
    ->prefix('admin/microstock/billing')
    ->name('admin.microstock.billing.')
    ->group(function (): void {
        Route::get('overview', [AdminBillingController::class, 'overview'])->name('overview');
        Route::get('usage-events', [AdminBillingController::class, 'usageEvents'])->name('usage-events');
    });
