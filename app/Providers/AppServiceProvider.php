<?php

namespace App\Providers;

use App\Console\Commands\MonolithConfigCacheCommand;
use App\Console\ConsoleSafetyServiceProvider;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use App\Repositories\Eloquent\EloquentCrudRepository;
use App\Support\Ai\AiUsageConsolidatorRegistry;
use App\Support\Billing\FulfillmentRegistry;
use App\Support\Broadcasting\ChannelRegistrarRegistry;
use App\Support\Encryption\LegacyColumnMigratorRegistry;
use App\Support\Http\AllowlistCorsService;
use Fruitcake\Cors\CorsService;
use Illuminate\Foundation\Console\ConfigCacheCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EloquentCrudRepositoryInterface::class, EloquentCrudRepository::class);

        $this->app->singleton(ConfigCacheCommand::class, MonolithConfigCacheCommand::class);

        // `HandleCors` injects this service. The stock implementation answers a
        // single-origin allowlist without checking the request origin; see the
        // class docblock for why that must not reach the browser.
        $this->app->bind(CorsService::class, AllowlistCorsService::class);

        // Kernel-owned registries: a packaged domain publishes seams into these
        // from its own provider, so neither the kernel nor a package imports the
        // other's classes. Singleton is correct — they are pure registration maps.
        $this->app->singleton(FulfillmentRegistry::class);
        $this->app->singleton(LegacyColumnMigratorRegistry::class);
        $this->app->singleton(ChannelRegistrarRegistry::class);
        $this->app->singleton(AiUsageConsolidatorRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $safety = new ConsoleSafetyServiceProvider;

        $safety->registerDestructiveDatabaseGuard();
        $safety->registerTestCommandConfigClear();
    }
}
