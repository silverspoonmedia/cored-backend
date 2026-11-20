<?php

use Illuminate\Support\Facades\Route;

// Client-specific server routes (no Blade UI — JSON, redirects and provider
// callbacks only). The package endpoints are already mounted by the loader.
Route::middleware('web')->group(function (): void {
    //
});
