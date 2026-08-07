<?php

use App\Domain\Vtual\Http\Controllers\Web\VtuberProfileController;
use Illuminate\Support\Facades\Route;

Route::get('vtual/vtuber', [VtuberProfileController::class, 'index'])
    ->name('vtual.vtuber.index');

Route::get('vtual/vtuber/{creator}', [VtuberProfileController::class, 'show'])
    ->name('vtual.vtuber.show');
