<?php

use App\Domain\Ai\Http\Controllers\API\V1\AiConnectionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('ai')->name('ai.')->group(function (): void {
    Route::middleware('permission:view ai connections')->group(function (): void {
        Route::get('connections', [AiConnectionController::class, 'index'])
            ->name('connections.index');
        Route::get('connections/{connection}', [AiConnectionController::class, 'show'])
            ->whereUuid('connection')
            ->name('connections.show');
    });

    Route::middleware('permission:manage ai connections')->group(function (): void {
        Route::post('connections', [AiConnectionController::class, 'store'])
            ->name('connections.store');
        Route::patch('connections/{connection}', [AiConnectionController::class, 'update'])
            ->whereUuid('connection')
            ->name('connections.update');
        Route::delete('connections/{connection}', [AiConnectionController::class, 'destroy'])
            ->whereUuid('connection')
            ->name('connections.destroy');
        Route::post('connections/{connection}/default', [AiConnectionController::class, 'setDefault'])
            ->whereUuid('connection')
            ->name('connections.default');
        Route::post('connections/{connection}/test', [AiConnectionController::class, 'test'])
            ->whereUuid('connection')
            ->name('connections.test');
    });
});
