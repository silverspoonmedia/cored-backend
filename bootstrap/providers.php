<?php

use App\Providers\AppServiceProvider;
use App\Providers\Domain\ActivityLogServiceProvider;
use App\Providers\Domain\ErrorLogServiceProvider;
use App\Providers\Domain\FileHostingServiceProvider;
use App\Providers\Domain\IdentityServiceProvider;
use App\Providers\Domain\MicrostockServiceProvider;
use App\Providers\Domain\NavigationServiceProvider;
use App\Providers\Domain\NotificationServiceProvider;
use App\Providers\Domain\PaymentGatewayServiceProvider;
use App\Providers\Domain\ServiceServiceProvider;
use App\Providers\Domain\VtualServiceProvider;
use App\Providers\DomainAccessServiceProvider;
use App\Providers\EncryptionServiceProvider;
use App\Providers\ProductServiceProvider;

return [
    // Kernel
    AppServiceProvider::class,
    EncryptionServiceProvider::class,
    ProductServiceProvider::class,
    DomainAccessServiceProvider::class,

    // Core domains (config/domains.php kind=core)
    IdentityServiceProvider::class,
    NavigationServiceProvider::class,
    NotificationServiceProvider::class,
    ActivityLogServiceProvider::class,
    ErrorLogServiceProvider::class,
    PaymentGatewayServiceProvider::class,
    ServiceServiceProvider::class,

    // Product domains (kind=product). domain:make appends; keep product last.
    FileHostingServiceProvider::class,
    VtualServiceProvider::class,
    MicrostockServiceProvider::class,
];
