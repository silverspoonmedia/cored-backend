<?php

use App\Http\Controllers\API\PlaygroundController;
use App\Http\Controllers\API\PlaygroundOrderController;
use Illuminate\Support\Facades\Route;

Route::get('ping', [PlaygroundController::class, 'ping']);

// Playground routes: demo CRUD surface for testing. Disabled in production for
// security (no authentication/authorization). See SECURITY_AUDIT.md FINDING-001.
if (! app()->environment('production')) {
    Route::prefix('playground')->group(function () {
        // Namespaced route names (`playground.users.*`) so the demo surface does not
        // squat the generic `users.*` names used by the web admin UI.
        Route::apiResource('users', PlaygroundController::class)
            ->except(['create', 'edit'])
            ->names('playground.users');

        // Mass-delete (declared before the resource so the literal segment is not
        // captured by the `{order}` wildcard). Ids ride in the request body.
        Route::delete('orders/bulk', [PlaygroundOrderController::class, 'bulkDestroy'])
            ->name('playground.orders.bulk-destroy');

        // Master-detail reference CRUD (Order + OrderDetail). Standard five-action
        // resource; the atomic two-table write lives in OrderService/OrderRepository.
        Route::apiResource('orders', PlaygroundOrderController::class)
            ->except(['create', 'edit'])
            ->names('playground.orders');
    });
}
