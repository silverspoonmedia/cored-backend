<?php

use App\Support\Domain\DomainManager;

// Core domains (config/domains.php kind=core). Order matches PLATFORM_CORE + Service.
//
// Packaged core domains (Navigation, Notification, Audit, Billing, AI) keep their
// routes inside their Composer package, so the path comes from the registry
// rather than a literal this checkout happens to have. Domains still living in
// app/Domain/* keep their require line: the loader stays the single place a
// version's endpoint set is assembled.

// Core packages, in registry order (packages/*/routes/*.php). Identity is the
// first entry: the kernel's own auth/user surface ships in `silverspoonmedia/platform`.
foreach (app(DomainManager::class)->packageApiRoutePaths() as $packageRouteFile) {
    require $packageRouteFile;
}

// Kernel product registry (App\Support\Product) — cross-domain access layer.
require __DIR__.'/v1/product-activations.php';

// Kernel domain-access registry (App\Support\DomainAccess) — product visibility gate.
require __DIR__.'/v1/domain-access.php';

// Kernel settings registry (App\Support\Settings) — operational runtime settings.
require __DIR__.'/v1/settings.php';

// Broadcast auth for cross-origin JWT clients (Vue-Workers SPA).
require __DIR__.'/v1/broadcasting.php';

// Read-only batch fan-in (Vue-Workers SPA invocation saver).
require __DIR__.'/v1/batch.php';

// Product domains (kind=product). domain:make appends here; keep product requires last.
require __DIR__.'/v1/archivd.php';
require __DIR__.'/v1/microstock.php';
require __DIR__.'/v1/admin-microstock.php';
