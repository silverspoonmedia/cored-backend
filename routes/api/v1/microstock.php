<?php

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
        Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

        Route::post('assets/{file}/analyze', [MetadataController::class, 'analyze'])
            ->whereUuid('file')->name('assets.analyze');
        Route::get('assets/{file}/metadata', [MetadataController::class, 'show'])
            ->whereUuid('file')->name('assets.metadata.show');
        Route::patch('assets/{file}/metadata', [MetadataController::class, 'update'])
            ->whereUuid('file')->name('assets.metadata.update');

        Route::get('credentials', [CredentialController::class, 'index'])->name('credentials.index');
        Route::post('credentials', [CredentialController::class, 'store'])->name('credentials.store');
        Route::delete('credentials/{credential}', [CredentialController::class, 'destroy'])
            ->whereUuid('credential')->name('credentials.destroy');
    });
