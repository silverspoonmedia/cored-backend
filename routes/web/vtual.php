<?php

use App\Domain\Vtual\Http\Controllers\Web\PlatformCredentialController;
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

// Admin: platform credentials (API enforces Root/Admin perms via ConsumesApi).
Route::get('vtual/admin/platform-credentials', [PlatformCredentialController::class, 'index'])
    ->name('vtual.admin.credentials.index');
Route::get('vtual/admin/platforms/{platform}/credentials', [PlatformCredentialController::class, 'showPlatform'])
    ->whereUuid('platform')
    ->name('vtual.admin.credentials.platform');
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
