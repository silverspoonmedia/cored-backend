<?php

use App\Support\Domain\DomainManager;

/*
|--------------------------------------------------------------------------
| API v1 — core packages, then client routes
|--------------------------------------------------------------------------
| Every core domain ships its endpoints inside its Composer package, including
| the kernel's cross-domain surface (`vendor/silverspoonmedia/platform/routes/
| kernel-*.php`: settings, product activations, domain access, broadcast auth,
| batch). `config/domains.php` names the package paths; `DomainManager` resolves
| them through Composer, so this loader is the whole assembly step and needs no
| per-package `require`.
|
| Client-specific endpoints belong at the bottom, after the packages, so a route
| this project adds never shadows a kernel one.
*/

foreach (app(DomainManager::class)->packageApiRoutePaths() as $packageRouteFile) {
    require $packageRouteFile;
}

// Client-specific versioned API v1 routes.
require __DIR__.'/v1/client.php';
