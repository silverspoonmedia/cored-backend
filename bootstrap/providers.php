<?php

use App\Providers\AppServiceProvider;
use App\Providers\Domain\ArchivdServiceProvider;
use App\Providers\Domain\IdentityServiceProvider;
use App\Providers\Domain\MicrostockServiceProvider;
use App\Providers\DomainAccessServiceProvider;
use App\Providers\EncryptionServiceProvider;
use App\Providers\ProductServiceProvider;
use App\Providers\SettingsServiceProvider;

return [
    // Kernel
    AppServiceProvider::class,
    EncryptionServiceProvider::class,
    ProductServiceProvider::class,
    DomainAccessServiceProvider::class,
    SettingsServiceProvider::class,

    // Core domains (config/domains.php kind=core)
    // Navigation, Notification, Audit, Billing and AI are Composer packages:
    // their providers are auto-discovered from packages/*/composer.json
    // extra.laravel.providers.
    IdentityServiceProvider::class,

    // Product domains (kind=product). domain:make appends; keep product last.
    ArchivdServiceProvider::class,
    MicrostockServiceProvider::class,
];
