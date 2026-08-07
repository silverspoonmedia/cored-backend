<?php

use App\Domain\Vtual\Http\Controllers\Web\CreatorProfilesController;
use Illuminate\Support\Facades\Route;

Route::get('vtual/profiles', [CreatorProfilesController::class, 'index'])
    ->name('vtual.profiles.index');

Route::get('vtual/profiles/{creator}', [CreatorProfilesController::class, 'show'])
    ->name('vtual.profiles.show');
Route::patch('vtual/profiles/{creator}', [CreatorProfilesController::class, 'update'])
    ->name('vtual.profiles.update');
Route::post('vtual/profiles/{creator}/merge', [CreatorProfilesController::class, 'merge'])
    ->name('vtual.profiles.merge');
Route::post('vtual/profiles/{creator}/channels', [CreatorProfilesController::class, 'storeChannel'])
    ->name('vtual.profiles.channels.store');
Route::post('vtual/profiles/{creator}/channel-claims/{claim}/verify', [CreatorProfilesController::class, 'verifyChannelClaim'])
    ->whereUuid('claim')
    ->name('vtual.profiles.channel-claims.verify');
Route::post('vtual/profiles/{creator}/channel-claims/{claim}/verify-discord', [CreatorProfilesController::class, 'verifyChannelClaimDiscord'])
    ->whereUuid('claim')
    ->name('vtual.profiles.channel-claims.verify-discord');
Route::post('vtual/profiles/{creator}/channel-claims/{claim}/cancel', [CreatorProfilesController::class, 'cancelChannelClaim'])
    ->whereUuid('claim')
    ->name('vtual.profiles.channel-claims.cancel');
Route::post('vtual/profiles/{creator}/channel-claims/{claim}/accept-anyway', [CreatorProfilesController::class, 'acceptAnywayChannelClaim'])
    ->whereUuid('claim')
    ->name('vtual.profiles.channel-claims.accept-anyway');
Route::post('vtual/profiles/{creator}/channels/{channel}/refresh', [CreatorProfilesController::class, 'refreshChannel'])
    ->whereUuid('channel')
    ->name('vtual.profiles.channels.refresh');
Route::post('vtual/profiles/{creator}/channels/{channel}/archive', [CreatorProfilesController::class, 'archiveChannel'])
    ->whereUuid('channel')
    ->name('vtual.profiles.channels.archive');
Route::post('vtual/profiles/{creator}/channels/{channel}/restore', [CreatorProfilesController::class, 'restoreChannel'])
    ->whereUuid('channel')
    ->name('vtual.profiles.channels.restore');
Route::post('vtual/profiles/{creator}/channels/{channel}/promote', [CreatorProfilesController::class, 'promoteChannel'])
    ->whereUuid('channel')
    ->name('vtual.profiles.channels.promote');
Route::post('vtual/profiles/{creator}/links', [CreatorProfilesController::class, 'storeLink'])
    ->name('vtual.profiles.links.store');
Route::patch('vtual/profiles/{creator}/links/{link}', [CreatorProfilesController::class, 'updateLink'])
    ->whereUuid('link')
    ->name('vtual.profiles.links.update');
Route::delete('vtual/profiles/{creator}/links/{link}', [CreatorProfilesController::class, 'destroyLink'])
    ->whereUuid('link')
    ->name('vtual.profiles.links.destroy');
