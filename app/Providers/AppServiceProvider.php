<?php

namespace App\Providers;

use App\Console\Commands\MonolithConfigCacheCommand;
use App\Console\ConsoleSafetyServiceProvider;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use App\Repositories\Eloquent\EloquentCrudRepository;
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
