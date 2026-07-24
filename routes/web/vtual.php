<?php

use App\Domain\Vtual\Http\Controllers\Web\CreatorClaimController;
use App\Domain\Vtual\Http\Controllers\Web\CreatorProfileController;
use App\Domain\Vtual\Http\Controllers\Web\PlatformApiProbeController;
use App\Domain\Vtual\Http\Controllers\Web\PlatformCredentialController;
use App\Domain\Vtual\Http\Controllers\Web\VtualController;
use Illuminate\Support\Facades\Route;

Route::middleware('domain.access:Vtual')->group(function (): void {
    Route::get('vtual', [VtualController::class, 'index'])->name('vtual.index');
Route::post('vtual/creators', [VtualController::class, 'storeCreator'])->name('vtual.creators.store');
// Legacy creator show → profiles manage URL (product language).
Route::get('vtual/creators/{creator}', [CreatorProfileController::class, 'show'])
    ->whereUuid('creator')
    ->name('vtual.creators.show');
Route::post('vtual/creators/{creator}/channels', [CreatorProfileController::class, 'storeChannel'])
    ->whereUuid('creator')
    ->name('vtual.creators.channels.store');
Route::post('vtual/creators/{creator}/channels/{channel}/refresh', [CreatorProfileController::class, 'refreshChannel'])
    ->whereUuid(['creator', 'channel'])
    ->name('vtual.creators.channels.refresh');
Route::post('vtual/managements', [VtualController::class, 'storeManagement'])->name('vtual.managements.store');

// My vTuber profiles (list + manage).
Route::get('vtual/profiles', [CreatorProfileController::class, 'index'])->name('vtual.profiles.index');
Route::get('vtual/profiles/{creator}', [CreatorProfileController::class, 'show'])
    ->whereUuid('creator')
    ->name('vtual.profiles.show');
Route::patch('vtual/profiles/{creator}', [CreatorProfileController::class, 'update'])
    ->whereUuid('creator')
    ->name('vtual.profiles.update');
Route::post('vtual/profiles/{creator}/merge', [CreatorProfileController::class, 'merge'])
    ->whereUuid('creator')
    ->name('vtual.profiles.merge');
Route::post('vtual/profiles/{creator}/channels', [CreatorProfileController::class, 'storeChannel'])
    ->whereUuid('creator')
    ->name('vtual.profiles.channels.store');
Route::post('vtual/profiles/{creator}/channel-claims/{claim}/verify', [CreatorProfileController::class, 'verifyChannelClaim'])
    ->whereUuid(['creator', 'claim'])
    ->name('vtual.profiles.channel-claims.verify');
Route::post('vtual/profiles/{creator}/channel-claims/{claim}/cancel', [CreatorProfileController::class, 'cancelChannelClaim'])
    ->whereUuid(['creator', 'claim'])
    ->name('vtual.profiles.channel-claims.cancel');
Route::post('vtual/profiles/{creator}/channel-claims/{claim}/accept-anyway', [CreatorProfileController::class, 'acceptAnywayChannelClaim'])
    ->whereUuid(['creator', 'claim'])
    ->name('vtual.profiles.channel-claims.accept-anyway');
Route::post('vtual/profiles/{creator}/channels/{channel}/refresh', [CreatorProfileController::class, 'refreshChannel'])
    ->whereUuid(['creator', 'channel'])
    ->name('vtual.profiles.channels.refresh');
Route::post('vtual/profiles/{creator}/channels/{channel}/archive', [CreatorProfileController::class, 'archiveChannel'])
    ->whereUuid(['creator', 'channel'])
    ->name('vtual.profiles.channels.archive');
Route::post('vtual/profiles/{creator}/channels/{channel}/restore', [CreatorProfileController::class, 'restoreChannel'])
    ->whereUuid(['creator', 'channel'])
    ->name('vtual.profiles.channels.restore');
Route::post('vtual/profiles/{creator}/links', [CreatorProfileController::class, 'storeLink'])
    ->whereUuid('creator')
    ->name('vtual.profiles.links.store');
Route::patch('vtual/profiles/{creator}/links/{link}', [CreatorProfileController::class, 'updateLink'])
    ->whereUuid(['creator', 'link'])
    ->name('vtual.profiles.links.update');
Route::delete('vtual/profiles/{creator}/links/{link}', [CreatorProfileController::class, 'destroyLink'])
    ->whereUuid(['creator', 'link'])
    ->name('vtual.profiles.links.destroy');

// User: claim as vTuber. Staff: registry + pending queue.
Route::get('vtual/claims', [CreatorClaimController::class, 'index'])->name('vtual.claims.index');
Route::post('vtual/claims', [CreatorClaimController::class, 'store'])->name('vtual.claims.store');
Route::get('vtual/admin/registry', [CreatorClaimController::class, 'staffRegistry'])
    ->name('vtual.claims.staff');
Route::post('vtual/claims/orphan', [CreatorClaimController::class, 'storeOrphan'])
    ->name('vtual.claims.orphan');
Route::get('vtual/claims/{claim}', [CreatorClaimController::class, 'show'])
    ->whereUuid('claim')
    ->name('vtual.claims.show');
Route::post('vtual/claims/{claim}/verify', [CreatorClaimController::class, 'verify'])
    ->whereUuid('claim')
    ->name('vtual.claims.verify');
Route::post('vtual/claims/{claim}/cancel', [CreatorClaimController::class, 'cancel'])
    ->whereUuid('claim')
    ->name('vtual.claims.cancel');
Route::post('vtual/claims/{claim}/accept-anyway', [CreatorClaimController::class, 'acceptAnyway'])
    ->whereUuid('claim')
    ->name('vtual.claims.accept-anyway');

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

// Admin: live platform API probe (Root/Admin only — manage credential perm).
Route::middleware('permission:manage vtual platform credentials')->group(function (): void {
    Route::get('vtual/admin/platform-api-probe', [PlatformApiProbeController::class, 'show'])
        ->name('vtual.admin.platform-api-probe');
    Route::post('vtual/admin/platform-api-probe', [PlatformApiProbeController::class, 'run'])
        ->name('vtual.admin.platform-api-probe.run');
});
});
