<?php

use App\Support\DomainAccess\Http\Controllers\Web\DomainAccessController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view domain access')
    ->prefix('domain-access')
    ->name('domain-access.')
    ->group(function (): void {
        Route::get('/', [DomainAccessController::class, 'index'])->name('index');

        Route::middleware('permission:manage domain access')->group(function (): void {
            Route::put('{productKey}', [DomainAccessController::class, 'update'])->name('update');
        });
    });
