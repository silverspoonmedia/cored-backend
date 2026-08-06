<?php

use App\Domain\Vtual\Http\Controllers\Web\OrphanVtuberController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:Root|Admin|Moderator')->group(function (): void {
    Route::get('vtual/orphans', [OrphanVtuberController::class, 'index'])->name('vtual.orphans.index');
    Route::get('vtual/orphans/datatable', [OrphanVtuberController::class, 'datatable'])->name('vtual.orphans.datatable');
    Route::get('vtual/orphans/create', [OrphanVtuberController::class, 'create'])->name('vtual.orphans.create');
    Route::post('vtual/orphans', [OrphanVtuberController::class, 'store'])->name('vtual.orphans.store');
    Route::get('vtual/orphans/{creator}/edit', [OrphanVtuberController::class, 'edit'])
        ->whereUuid('creator')
        ->name('vtual.orphans.edit');
    Route::match(['put', 'patch'], 'vtual/orphans/{creator}', [OrphanVtuberController::class, 'update'])
        ->whereUuid('creator')
        ->name('vtual.orphans.update');
    Route::post('vtual/orphans/{creator}/channels', [OrphanVtuberController::class, 'storeChannel'])
        ->whereUuid('creator')
        ->name('vtual.orphans.channels.store');
    Route::delete('vtual/orphans/{creator}', [OrphanVtuberController::class, 'destroy'])
        ->whereUuid('creator')
        ->name('vtual.orphans.destroy');
});
