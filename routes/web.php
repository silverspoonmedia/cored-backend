<?php

use App\Support\Domain\DomainManager;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web surface (server-side only)
|--------------------------------------------------------------------------
| This project has no Blade UI; the SPA is the sole frontend and talks to
| /api/v1. What remains here are server endpoints:
|
|  - package endpoints an entry declares with a `web` key (the iPaymu return,
|    the Discord OAuth callback), required in registry order below;
|  - the SPA redirect and any client-specific server route.
*/

Route::get('/', fn () => redirect()->to(config('services.spa.url') ?: '/'));

foreach (app(DomainManager::class)->packageWebRoutePaths() as $packageWebRouteFile) {
    require $packageWebRouteFile;
}

// Client-specific server routes.
require __DIR__.'/web/client.php';
