<?php

use App\Providers\AppServiceProvider;
use App\Providers\Domain\ActivityLogServiceProvider;
use App\Providers\Domain\ApprovalServiceProvider;
use App\Providers\Domain\IdentityServiceProvider;
use App\Providers\Domain\NavigationServiceProvider;
use App\Providers\Domain\NotificationServiceProvider;
use App\Providers\Domain\PlaygroundServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    NavigationServiceProvider::class,
    NotificationServiceProvider::class,
    ActivityLogServiceProvider::class,
    PlaygroundServiceProvider::class,
    ApprovalServiceProvider::class,
];
