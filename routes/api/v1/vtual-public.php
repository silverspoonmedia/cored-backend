<?php

use App\Domain\Vtual\Http\Controllers\API\V1\PublicManagementController;
use App\Domain\Vtual\Http\Controllers\API\V1\PublicVtuberController;
use Illuminate\Support\Facades\Route;

// Guest-safe public vTual surface (landing page /vtual on the SPA).
// No auth, no domain.access, no product.active — same pattern as vtual-webhooks.php.
Route::middleware('throttle:60,1')->prefix('vtual/public')->name('vtual.public.')->group(function (): void {
    Route::get('landing', [PublicVtuberController::class, 'landing'])->name('landing');
    Route::get('stats', [PublicVtuberController::class, 'stats'])->name('stats');
    Route::get('live', [PublicVtuberController::class, 'live'])->name('live');
    Route::get('schedule', [PublicVtuberController::class, 'schedule'])->name('schedule');
    Route::get('events', [PublicVtuberController::class, 'events'])->name('events');
    Route::get('vtubers', [PublicVtuberController::class, 'index'])->name('vtubers.index');
    Route::get('contents', [PublicVtuberController::class, 'contents'])->name('contents.index');
    Route::get('vtubers/{creator}', [PublicVtuberController::class, 'show'])->name('vtubers.show');
    Route::get('managements/{management}', [PublicManagementController::class, 'show'])->name('managements.show');
});
