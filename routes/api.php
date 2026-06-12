<?php

use App\Http\Controllers\API\PlaygroundController;
use App\Http\Controllers\API\PlaygroundOrderController;
use Illuminate\Support\Facades\Route;

Route::get('ping', [PlaygroundController::class, 'ping']);

Route::prefix('playground')->group(function () {
    // Namespaced route names (`playground.users.*`) so the demo surface does not
    // squat the generic `users.*` names used by the web admin UI.
    Route::apiResource('users', PlaygroundController::class)
        ->except(['create', 'edit'])
        ->names('playground.users');

    // Master-detail reference CRUD (Order + OrderDetail). Standard five-action
    // resource; the atomic two-table write lives in OrderService/OrderRepository.
    Route::apiResource('orders', PlaygroundOrderController::class)
        ->except(['create', 'edit'])
        ->names('playground.orders');
});
