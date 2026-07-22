<?php

use App\Domain\Vtual\Http\Controllers\API\V1\ChannelController;
use App\Domain\Vtual\Http\Controllers\API\V1\CreatorController;
use App\Domain\Vtual\Http\Controllers\API\V1\ManagementController;
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
        Route::get('channels/{channel}', [ChannelController::class, 'show'])->whereUuid('channel')->name('channels.show');
    });

    Route::middleware('permission:create vtual creator')->group(function (): void {
        Route::post('creators', [CreatorController::class, 'store'])->name('creators.store');
    });

    Route::middleware('permission:edit vtual creator')->group(function (): void {
        Route::patch('creators/{creator}', [CreatorController::class, 'update'])->whereUuid('creator')->name('creators.update');
        Route::post('creators/{creator}/channels', [ChannelController::class, 'store'])
            ->whereUuid('creator')
            ->name('creators.channels.store');
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
        });
    });
});
