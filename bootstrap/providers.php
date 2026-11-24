<?php

use App\Providers\KernelServiceProvider;

/*
|--------------------------------------------------------------------------
| Application service providers
|--------------------------------------------------------------------------
| `KernelServiceProvider` is the kernel's runtime surface: config merges, the
| seam registries a package publishes into, the shared repository and the CORS
| service. It is registered here rather than auto-discovered, because the
| monolith vendors the same package and boots `AppServiceProvider` for that
| surface instead — running both would replace a resolved registry.
|
| `extra.laravel.providers` in each `silverspoonmedia/*` manifest carries the
| domain providers (Identity, Navigation, Notification, Audit, Billing, AI);
| Laravel's package discovery registers those, so do not repeat them here.
|
| Client-specific providers belong below the kernel.
*/

return [
    KernelServiceProvider::class,
];
