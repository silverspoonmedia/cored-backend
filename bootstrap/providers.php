<?php

use App\Providers\AppServiceProvider;
use App\Providers\Domain\ActivityLogServiceProvider;
use App\Providers\Domain\IdentityServiceProvider;
use App\Providers\Domain\NavigationServiceProvider;
use App\Providers\Domain\NotificationServiceProvider;
use App\Providers\Domain\PaymentGatewayServiceProvider;
use App\Providers\Domain\FileHostingServiceProvider;
use App\Providers\Domain\ServiceServiceProvider;
use App\Providers\EncryptionServiceProvider;

return [
    AppServiceProvider::class,
    EncryptionServiceProvider::class,
    IdentityServiceProvider::class,
    NavigationServiceProvider::class,
    NotificationServiceProvider::class,
    ActivityLogServiceProvider::class,
    PaymentGatewayServiceProvider::class,
    FileHostingServiceProvider::class,
    ServiceServiceProvider::class,
];