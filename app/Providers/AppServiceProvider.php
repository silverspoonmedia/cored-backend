<?php

namespace App\Providers;

use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use App\Repositories\Eloquent\EloquentCrudRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EloquentCrudRepositoryInterface::class, EloquentCrudRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
