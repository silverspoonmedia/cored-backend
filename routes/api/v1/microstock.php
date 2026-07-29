<?php

use App\Domain\Microstock\Http\Controllers\API\V1\BillingController;
use App\Domain\Microstock\Http\Controllers\API\V1\CollectionController;
use App\Domain\Microstock\Http\Controllers\API\V1\CredentialController;
use App\Domain\Microstock\Http\Controllers\API\V1\MetadataController;
use App\Domain\Microstock\Http\Controllers\API\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Microstock API routes (versioned)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api', 'domain.access:Microstock', 'product.active:Microstock'])
    ->prefix('microstock')
    ->name('microstock.')
    ->group(function (): void {
        Route::get('workspace', [WorkspaceController::class, 'show'])->name('workspace.show');
        Route::get('slots', [WorkspaceController::class, 'slots'])->name('slots.show');
        Route::get('billing', [BillingController::class, 'show'])->name('billing.show');
        Route::get('billing/ledger', [BillingController::class, 'ledger'])->name('billing.ledger');
        Route::get('billing/usage-events', [BillingController::class, 'usageEvents'])->name('billing.usage-events');
        Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

        Route::post('assets/{file}/analyze', [MetadataController::class, 'analyze'])
            ->whereUuid('file')->name('assets.analyze');
        Route::get('assets/{file}/metadata', [MetadataController::class, 'show'])
            ->whereUuid('file')->name('assets.metadata.show');
        Route::patch('assets/{file}/metadata', [MetadataController::class, 'update'])
            ->whereUuid('file')->name('assets.metadata.update');

        Route::post('collections/{folder}/lock', [CollectionController::class, 'lock'])
            ->whereUuid('folder')->name('collections.lock');
        Route::post('collections/{folder}/analyze', [CollectionController::class, 'analyze'])
            ->whereUuid('folder')->name('collections.analyze');
        Route::get('collections/{folder}/status', [CollectionController::class, 'status'])
            ->whereUuid('folder')->name('collections.status');
        Route::post('collections/{folder}/export', [CollectionController::class, 'export'])
            ->whereUuid('folder')->name('collections.export');
        Route::get('collections/{folder}/download', [CollectionController::class, 'download'])
            ->whereUuid('folder')->name('collections.download');

        Route::get('credentials', [CredentialController::class, 'index'])->name('credentials.index');
        Route::post('credentials', [CredentialController::class, 'store'])->name('credentials.store');
        Route::delete('credentials/{credential}', [CredentialController::class, 'destroy'])
            ->whereUuid('credential')->name('credentials.destroy');
    });
