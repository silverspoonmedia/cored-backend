<?php

use App\Providers\AppServiceProvider;
use App\Providers\Domain\ActivityLogServiceProvider;
use App\Providers\Domain\AiServiceProvider;
use App\Providers\Domain\ErrorLogServiceProvider;
use App\Providers\Domain\ArchivdServiceProvider;
use App\Providers\Domain\InferenceServiceProvider;
use App\Providers\Domain\IdentityServiceProvider;
use App\Providers\Domain\MicrostockServiceProvider;
use App\Providers\Domain\PaymentGatewayServiceProvider;
use App\Providers\Domain\ServiceServiceProvider;
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
    // Navigation + Notification are Composer packages: their providers are
    // auto-discovered from packages/*/composer.json extra.laravel.providers.
    IdentityServiceProvider::class,
    ActivityLogServiceProvider::class,
    ErrorLogServiceProvider::class,
    PaymentGatewayServiceProvider::class,
    ServiceServiceProvider::class,
    InferenceServiceProvider::class,
    AiServiceProvider::class,

    // Product domains (kind=product). domain:make appends; keep product last.
    ArchivdServiceProvider::class,
    MicrostockServiceProvider::class,
];