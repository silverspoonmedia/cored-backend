<?php

use App\Domain\Vtual\Http\Controllers\Web\AdminRegistryController;
use App\Domain\Vtual\Http\Controllers\Web\PlatformApiProbeController;
use App\Domain\Vtual\Http\Controllers\Web\PlatformCredentialController;
use Illuminate\Support\Facades\Route;

Route::middleware(['domain.access:Vtual', 'role:Root|Admin|Moderator'])->group(function (): void {
    Route::get('vtual/admin/registry', [AdminRegistryController::class, 'index'])
        ->name('vtual.admin.registry');
    Route::post('vtual/admin/registry/orphan', [AdminRegistryController::class, 'storeOrphan'])
        ->name('vtual.admin.registry.orphan');
    Route::get('vtual/admin/claims/{claim}', [AdminRegistryController::class, 'showClaim'])
        ->whereUuid('claim')
        ->name('vtual.admin.claims.show');
    Route::post('vtual/admin/claims/{claim}/accept-anyway', [AdminRegistryController::class, 'acceptAnyway'])
        ->whereUuid('claim')
        ->name('vtual.admin.claims.accept-anyway');

    Route::middleware('role:Root|Admin')->group(function (): void {
        Route::get('vtual/admin/platform-credentials', [PlatformCredentialController::class, 'index'])
            ->name('vtual.admin.credentials.index');
        Route::get('vtual/admin/platforms/{platform}/credentials', [PlatformCredentialController::class, 'showPlatform'])
            ->whereUuid('platform')
            ->name('vtual.admin.credentials.platform');
        Route::get('vtual/admin/platforms/{platform}/credentials/create', [PlatformCredentialController::class, 'create'])
            ->whereUuid('platform')
            ->name('vtual.admin.credentials.create');
        Route::get('vtual/admin/credentials/{credential}/edit', [PlatformCredentialController::class, 'edit'])
            ->whereUuid('credential')
            ->name('vtual.admin.credentials.edit');
        Route::post('vtual/admin/platforms/{platform}/credentials', [PlatformCredentialController::class, 'store'])
            ->whereUuid('platform')
            ->name('vtual.admin.credentials.store');
        Route::patch('vtual/admin/credentials/{credential}', [PlatformCredentialController::class, 'update'])
            ->whereUuid('credential')
            ->name('vtual.admin.credentials.update');
        Route::delete('vtual/admin/credentials/{credential}', [PlatformCredentialController::class, 'destroy'])
            ->whereUuid('credential')
            ->name('vtual.admin.credentials.destroy');
        Route::post('vtual/admin/credentials/{credential}/disable', [PlatformCredentialController::class, 'disable'])
            ->whereUuid('credential')
            ->name('vtual.admin.credentials.disable');
        Route::post('vtual/admin/credentials/{credential}/test', [PlatformCredentialController::class, 'test'])
            ->whereUuid('credential')
            ->name('vtual.admin.credentials.test');

        Route::middleware('permission:manage vtual platform credentials')->group(function (): void {
            Route::get('vtual/admin/platform-api-probe', [PlatformApiProbeController::class, 'show'])
                ->name('vtual.admin.platform-api-probe');
            Route::post('vtual/admin/platform-api-probe', [PlatformApiProbeController::class, 'run'])
                ->name('vtual.admin.platform-api-probe.run');
        });
    });
});
