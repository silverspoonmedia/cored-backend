<?php

// Core domains (config/domains.php kind=core). Order matches PLATFORM_CORE + Service.
require __DIR__.'/v1/identity.php';
require __DIR__.'/v1/navigation.php';
require __DIR__.'/v1/notification.php';
require __DIR__.'/v1/activity-log.php';
require __DIR__.'/v1/error-log.php';
require __DIR__.'/v1/payment-gateway.php';
require __DIR__.'/v1/service.php';

// Kernel product registry (App\Support\Product) — cross-domain access layer.
require __DIR__.'/v1/product-activations.php';

// Kernel domain-access registry (App\Support\DomainAccess) — product visibility gate.
require __DIR__.'/v1/domain-access.php';

// Product domains (kind=product). domain:make appends here; keep product requires last.
require __DIR__.'/v1/file-hosting.php';
require __DIR__.'/v1/vtual.php';

