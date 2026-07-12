<?php

namespace App\Providers;

use App\Console\ConsoleSafetyServiceProvider;
use App\Console\Commands\MonolithConfigCacheCommand;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use App\Repositories\Eloquent\EloquentCrudRepository;
use App\Support\DataTable\DataTableResponseFactory;
use App\Support\Web\WebLayout;
use Illuminate\Foundation\Console\ConfigCacheCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EloquentCrudRepositoryInterface::class, EloquentCrudRepository::class);
        $this->app->singleton(DataTableResponseFactory::class);

        $this->app->singleton(ConfigCacheCommand::class, MonolithConfigCacheCommand::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::anonymousComponentPath(WebLayout::componentsPath(), WebLayout::name());

        $safety = new ConsoleSafetyServiceProvider;

        $safety->registerDestructiveDatabaseGuard();
        $safety->registerTestCommandConfigClear();
    }
}
