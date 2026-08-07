<?php

use App\Domain\Vtual\Http\Controllers\Web\VtuberProfileController;
use Illuminate\Support\Facades\Route;

Route::get('vtual/vtuber', [VtuberProfileController::class, 'index'])
    ->name('vtual.vtuber.index');

Route::post('vtual/vtuber/{creator}/claim/start', [VtuberProfileController::class, 'startClaim'])
    ->name('vtual.vtuber.claim.start');
Route::post('vtual/vtuber/{creator}/claim/verify', [VtuberProfileController::class, 'verifyClaim'])
    ->name('vtual.vtuber.claim.verify');
Route::post('vtual/vtuber/{creator}/claim/cancel', [VtuberProfileController::class, 'cancelClaim'])
    ->name('vtual.vtuber.claim.cancel');

Route::get('vtual/vtuber/{creator}/claim', [VtuberProfileController::class, 'claim'])
    ->name('vtual.vtuber.claim');

Route::get('vtual/vtuber/{creator}', [VtuberProfileController::class, 'show'])
    ->name('vtual.vtuber.show');
