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
use App\Support\Settings\SettingCatalog;
use App\Support\Navigation\MenuContributorRegistry;
use Illuminate\Foundation\Console\ConfigCacheCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Guarded bindings: the kernel's `KernelServiceProvider` declares the
        // same surface for a consumer install. In the monolith this provider
        // runs first, so the kernel's copy is a no-op — but the reverse does not
        // hold: an unguarded `singleton()` here after a package published its
        // seam would forget the instance and empty the registry.
        if (! $this->app->bound(EloquentCrudRepositoryInterface::class)) {
            $this->app->bind(EloquentCrudRepositoryInterface::class, EloquentCrudRepository::class);
        }

        $this->app->singleton(ConfigCacheCommand::class, MonolithConfigCacheCommand::class);

        // `HandleCors` injects this service. The stock implementation answers a
        // single-origin allowlist without checking the request origin; see the
        // class docblock for why that must not reach the browser.
        if (! $this->app->bound(CorsService::class)) {
            $this->app->bind(CorsService::class, AllowlistCorsService::class);
        }

        // Kernel-owned registries: a packaged domain publishes seams into these
        // from its own provider, so neither the kernel nor a package imports the
        // other's classes. Singleton is correct — they are pure registration maps.
        foreach ([
            FulfillmentRegistry::class,
            LegacyColumnMigratorRegistry::class,
            ChannelRegistrarRegistry::class,
            AiUsageConsolidatorRegistry::class,
            SettingCatalog::class,
            MenuContributorRegistry::class,
        ] as $registry) {
            if (! $this->app->bound($registry)) {
                $this->app->singleton($registry);
            }
        }
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
