<?php

use App\Providers\AppServiceProvider;
use App\Providers\Domain\ArchivdServiceProvider;
use App\Providers\Domain\MicrostockServiceProvider;

return [
    // Kernel
    AppServiceProvider::class,

    // Core domains (config/domains.php kind=core)
    // Navigation, Notification, Audit, Billing, AI and the platform kernel
    // (Identity, Support, Http, Repositories, platform providers) are Composer
    // packages: their providers are auto-discovered from packages/*/composer.json
    // extra.laravel.providers.

    // Product domains (kind=product). domain:make appends; keep product last.
    ArchivdServiceProvider::class,
    MicrostockServiceProvider::class,
];
