<?php

use App\Providers\AppServiceProvider;
use App\Providers\Domain\ActivityLogServiceProvider;
use App\Providers\Domain\ApprovalServiceProvider;
use App\Providers\Domain\IdentityServiceProvider;
use App\Providers\Domain\NavigationServiceProvider;
use App\Providers\Domain\NotificationServiceProvider;
use App\Providers\Domain\PaymentGatewayServiceProvider;
use App\Providers\Domain\PlaygroundServiceProvider;
use App\Providers\Domain\ProductServiceProvider;
use App\Providers\Domain\FileHostingServiceProvider;
use App\Providers\EncryptionServiceProvider;

return [
    AppServiceProvider::class,
    EncryptionServiceProvider::class,
    IdentityServiceProvider::class,
    NavigationServiceProvider::class,
    NotificationServiceProvider::class,
    ActivityLogServiceProvider::class,
    PlaygroundServiceProvider::class,
    ApprovalServiceProvider::class,
    PaymentGatewayServiceProvider::class,
    ProductServiceProvider::class,
    FileHostingServiceProvider::class,
];