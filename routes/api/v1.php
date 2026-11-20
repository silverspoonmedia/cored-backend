<?php

use App\Support\Domain\DomainManager;

// Core domains (config/domains.php kind=core). Order matches PLATFORM_CORE + Service.
//
// Packaged core domains (Identity, Navigation, Notification, Audit, Billing, AI) keep
// their routes inside their Composer package — including the kernel's cross-domain
// surface (`routes/kernel-*.php`: settings, product activations, domain access,
// broadcast auth, batch) — so the path comes from the registry rather than a literal
// this checkout happens to have. `packageApiRoutePaths()` resolves it through
// Composer, so the same loader works here and in a consumer install.
//
// Domains still living in app/Domain/* keep their require line: the loader stays the
// single place a version's endpoint set is assembled.

foreach (app(DomainManager::class)->packageApiRoutePaths() as $packageRouteFile) {
    require $packageRouteFile;
}

// Product domains (kind=product). domain:make appends here; keep product requires last.
require __DIR__.'/v1/archivd.php';
require __DIR__.'/v1/microstock.php';
require __DIR__.'/v1/admin-microstock.php';
