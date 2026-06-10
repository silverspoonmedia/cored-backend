<?php

use App\Http\Controllers\API\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('ping', [PlaygroundController::class, 'ping']);

Route::prefix('playground')->group(function () {
    // Namespaced route names (`playground.users.*`) so the demo surface does not
    // squat the generic `users.*` names used by the web admin UI.
    Route::apiResource('users', PlaygroundController::class)
        ->except(['create', 'edit'])
        ->names('playground.users');
});
