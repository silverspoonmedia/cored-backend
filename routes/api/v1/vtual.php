<?php

use App\Domain\Vtual\Http\Controllers\API\V1\ChannelClaimController;
use App\Domain\Vtual\Http\Controllers\API\V1\ChannelController;
use App\Domain\Vtual\Http\Controllers\API\V1\CreatorClaimController;
use App\Domain\Vtual\Http\Controllers\API\V1\CreatorController;
use App\Domain\Vtual\Http\Controllers\API\V1\ExternalLinkController;
use App\Domain\Vtual\Http\Controllers\API\V1\ManagementController;
use App\Domain\Vtual\Http\Controllers\API\V1\PlatformApiProbeController;
use App\Domain\Vtual\Http\Controllers\API\V1\PlatformController;
use App\Domain\Vtual\Http\Controllers\API\V1\PlatformCredentialController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'product.active:Vtual'])->prefix('vtual')->name('vtual.')->group(function (): void {
    Route::middleware('permission:view vtual creator')->group(function (): void {
        Route::get('platforms', [PlatformController::class, 'index'])->name('platforms.index');
        Route::get('creators', [CreatorController::class, 'index'])->name('creators.index');
        Route::get('creators/{creator}', [CreatorController::class, 'show'])->whereUuid('creator')->name('creators.show');
        Route::get('creators/{creator}/channels', [ChannelController::class, 'index'])
            ->whereUuid('creator')
            ->name('creators.channels.index');
        Route::get('creators/{creator}/channels/archived', [ChannelController::class, 'archivedIndex'])
            ->whereUuid('creator')
            ->name('creators.channels.archived');
        Route::get('creators/{creator}/channel-claims', [ChannelClaimController::class, 'index'])
            ->whereUuid('creator')
            ->name('creators.channel-claims.index');
        Route::get('creators/{creator}/links', [ExternalLinkController::class, 'index'])
            ->whereUuid('creator')
            ->name('creators.links.index');
        Route::get('channels/{channel}', [ChannelController::class, 'show'])->whereUuid('channel')->name('channels.show');
        Route::get('channel-claims/{channelClaim}', [ChannelClaimController::class, 'show'])
            ->whereUuid('channelClaim')
            ->name('channel-claims.show');
    });

    Route::middleware('permission:create vtual creator')->group(function (): void {
        Route::post('creators', [CreatorController::class, 'store'])->name('creators.store');
    });

    Route::middleware('permission:manage any vtual resource')->group(function (): void {
        Route::post('creators/orphan', [CreatorController::class, 'storeOrphan'])->name('creators.orphan');
        Route::get('claims/pending', [CreatorClaimController::class, 'pending'])->name('claims.pending');
        Route::post('claims/{claim}/accept-anyway', [CreatorClaimController::class, 'acceptAnyway'])
            ->whereUuid('claim')
            ->name('claims.accept-anyway');
        Route::post('channel-claims/{channelClaim}/accept-anyway', [ChannelClaimController::class, 'acceptAnyway'])
            ->whereUuid('channelClaim')
            ->name('channel-claims.accept-anyway');
    });

    // Claim existing orphan creator (bio token / Accept Anyway).
    Route::middleware('permission:claim vtual creator')->group(function (): void {
        Route::get('claims/claimable-creators', [CreatorClaimController::class, 'claimable'])
            ->name('claims.claimable');
        Route::get('claims/mine', [CreatorClaimController::class, 'mine'])->name('claims.mine');
        Route::post('claims', [CreatorClaimController::class, 'store'])->name('claims.store');
        Route::get('claims/{claim}', [CreatorClaimController::class, 'show'])
            ->whereUuid('claim')
            ->name('claims.show');
        Route::post('claims/{claim}/verify', [CreatorClaimController::class, 'verify'])
            ->whereUuid('claim')
            ->name('claims.verify');
        Route::post('claims/{claim}/cancel', [CreatorClaimController::class, 'cancel'])
            ->whereUuid('claim')
            ->name('claims.cancel');
    });

    Route::middleware('permission:edit vtual creator')->group(function (): void {
        Route::patch('creators/{creator}', [CreatorController::class, 'update'])->whereUuid('creator')->name('creators.update');
        // Absorb source into destination (owner on both or manage any).
        Route::post('creators/{creator}/merge', [CreatorController::class, 'merge'])
            ->whereUuid('creator')
            ->name('creators.merge');
        // POST channels: staff seed active / owner starts bio claim.
        Route::post('creators/{creator}/channels', [ChannelClaimController::class, 'store'])
            ->whereUuid('creator')
            ->name('creators.channels.store');
        Route::post('channel-claims/{channelClaim}/verify', [ChannelClaimController::class, 'verify'])
            ->whereUuid('channelClaim')
            ->name('channel-claims.verify');
        Route::post('channel-claims/{channelClaim}/cancel', [ChannelClaimController::class, 'cancel'])
            ->whereUuid('channelClaim')
            ->name('channel-claims.cancel');
        Route::post('channels/{channel}/refresh', [ChannelController::class, 'refresh'])
            ->whereUuid('channel')
            ->name('channels.refresh');
        Route::post('channels/{channel}/archive', [ChannelController::class, 'archive'])
            ->whereUuid('channel')
            ->name('channels.archive');
        Route::post('channels/{channel}/restore', [ChannelController::class, 'restore'])
            ->whereUuid('channel')
            ->name('channels.restore');
        Route::post('creators/{creator}/links', [ExternalLinkController::class, 'store'])
            ->whereUuid('creator')
            ->name('creators.links.store');
        Route::patch('links/{link}', [ExternalLinkController::class, 'update'])
            ->whereUuid('link')
            ->name('links.update');
        Route::delete('links/{link}', [ExternalLinkController::class, 'destroy'])
            ->whereUuid('link')
            ->name('links.destroy');
    });

    Route::middleware('permission:delete vtual creator')->group(function (): void {
        Route::delete('creators/{creator}', [CreatorController::class, 'destroy'])->whereUuid('creator')->name('creators.destroy');
    });

    Route::middleware('permission:view vtual management')->group(function (): void {
        Route::get('managements', [ManagementController::class, 'index'])->name('managements.index');
        Route::get('managements/{management}', [ManagementController::class, 'show'])
            ->whereUuid('management')
            ->name('managements.show');
    });

    Route::middleware('permission:create vtual management')->group(function (): void {
        Route::post('managements', [ManagementController::class, 'store'])->name('managements.store');
    });

    Route::middleware('permission:edit vtual management')->group(function (): void {
        Route::patch('managements/{management}', [ManagementController::class, 'update'])
            ->whereUuid('management')
            ->name('managements.update');
        Route::post('managements/{management}/creators', [ManagementController::class, 'assignCreator'])
            ->whereUuid('management')
            ->name('managements.creators.assign');
        Route::delete('managements/{management}/creators/{creator}', [ManagementController::class, 'unassignCreator'])
            ->whereUuid(['management', 'creator'])
            ->name('managements.creators.unassign');
    });

    // Admin: platform integration credentials (Root/Admin core perms only).
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::middleware('permission:view vtual platform credentials')->group(function (): void {
            Route::get('platforms/{platform}/credentials', [PlatformCredentialController::class, 'index'])
                ->whereUuid('platform')
                ->name('platforms.credentials.index');
            Route::get('credentials/{credential}', [PlatformCredentialController::class, 'show'])
                ->whereUuid('credential')
                ->name('credentials.show');
        });

        Route::middleware('permission:manage vtual platform credentials')->group(function (): void {
            Route::post('platforms/{platform}/credentials', [PlatformCredentialController::class, 'store'])
                ->whereUuid('platform')
                ->name('platforms.credentials.store');
            Route::patch('credentials/{credential}', [PlatformCredentialController::class, 'update'])
                ->whereUuid('credential')
                ->name('credentials.update');
            Route::delete('credentials/{credential}', [PlatformCredentialController::class, 'destroy'])
                ->whereUuid('credential')
                ->name('credentials.destroy');
            Route::post('credentials/{credential}/disable', [PlatformCredentialController::class, 'disable'])
                ->whereUuid('credential')
                ->name('credentials.disable');
            Route::post('credentials/{credential}/test', [PlatformCredentialController::class, 'test'])
                ->whereUuid('credential')
                ->name('credentials.test');
            // Manual PlatformApiGateway probe (Root/Admin). Hits live YouTube/Twitch.
            Route::post('platform-api/probe', PlatformApiProbeController::class)
                ->name('platform-api.probe');
        });
    });
});
