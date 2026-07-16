<?php

use App\Domain\Playground\Http\Controllers\API\PlaygroundController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Playground API (non-production)
|--------------------------------------------------------------------------
| Order master-detail sell path retired in favour of Service domain checkout.
| Users demo CRUD remains for reference patterns.
*/
if (! app()->environment('production')) {
    Route::prefix('playground')->group(function (): void {
        Route::apiResource('users', PlaygroundController::class)
            ->except(['create', 'edit'])
            ->names('playground.users');
    });
}
