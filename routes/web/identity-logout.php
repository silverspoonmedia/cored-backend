<?php

use App\Domain\Identity\Http\Controllers\Web\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
| POST /logout stays at site root (not /apps/logout) next to guest auth surface.
*/
Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
