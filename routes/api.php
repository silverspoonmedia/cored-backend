<?php

use App\Http\Controllers\API\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('ping', [PlaygroundController::class, 'ping']);

Route::prefix('playground')->group(function () {
    Route::apiResource('users', PlaygroundController::class)->except(['create', 'edit']);
});
