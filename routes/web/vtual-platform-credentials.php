<?php

use App\Domain\Vtual\Http\Controllers\Web\PlatformApiProbeController;
use App\Domain\Vtual\Http\Controllers\Web\PlatformCredentialController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:Root|Admin')->group(function (): void {
    Route::get('vtual/platform-credentials', [PlatformCredentialController::class, 'index'])
        ->name('vtual.platform-credentials.index');
    Route::get('vtual/platform-credentials/platforms/{platform}', [PlatformCredentialController::class, 'showPlatform'])
        ->whereUuid('platform')
        ->name('vtual.platform-credentials.platform');
    Route::get('vtual/platform-credentials/platforms/{platform}/create', [PlatformCredentialController::class, 'create'])
        ->whereUuid('platform')
        ->name('vtual.platform-credentials.create');
    Route::post('vtual/platform-credentials/platforms/{platform}', [PlatformCredentialController::class, 'store'])
        ->whereUuid('platform')
        ->name('vtual.platform-credentials.store');
    Route::get('vtual/platform-credentials/credentials/{credential}/edit', [PlatformCredentialController::class, 'edit'])
        ->whereUuid('credential')
        ->name('vtual.platform-credentials.edit');
    Route::patch('vtual/platform-credentials/credentials/{credential}', [PlatformCredentialController::class, 'update'])
        ->whereUuid('credential')
        ->name('vtual.platform-credentials.update');
    Route::delete('vtual/platform-credentials/credentials/{credential}', [PlatformCredentialController::class, 'destroy'])
        ->whereUuid('credential')
        ->name('vtual.platform-credentials.destroy');
    Route::post('vtual/platform-credentials/credentials/{credential}/revoke', [PlatformCredentialController::class, 'revoke'])
        ->whereUuid('credential')
        ->name('vtual.platform-credentials.revoke');
    Route::post('vtual/platform-credentials/credentials/{credential}/test', [PlatformCredentialController::class, 'test'])
        ->whereUuid('credential')
        ->name('vtual.platform-credentials.test');

    Route::middleware('permission:manage vtual platform credentials')->group(function (): void {
        Route::get('vtual/platform-credentials/probe', [PlatformApiProbeController::class, 'show'])
            ->name('vtual.platform-credentials.probe');
        Route::post('vtual/platform-credentials/probe', [PlatformApiProbeController::class, 'run'])
            ->name('vtual.platform-credentials.probe.run');
    });
});
