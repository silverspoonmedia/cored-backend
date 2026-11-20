<?php

use Illuminate\Support\Facades\Route;

// Client-specific versioned API v1 endpoints. The core surface is already
// mounted by the loader above; this file is the only place a project adds its
// own routes, so the package list never has to be edited by hand.
Route::middleware('api')->group(function (): void {
    //
});
