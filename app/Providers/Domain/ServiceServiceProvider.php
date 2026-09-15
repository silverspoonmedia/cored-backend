<?php

namespace App\Providers\Domain;

use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Policies\ServicePolicy;
use App\Domain\Service\Policies\ServicePurchasePolicy;
use App\Domain\Service\Services\ServiceEntitlementService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ServiceEntitlementService::class);
    }

    public function boot(): void
    {
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(ServicePurchase::class, ServicePurchasePolicy::class);
    }
}
