<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentCrudRepository;
use App\Repositories\Eloquent\MenuRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EloquentCrudRepositoryInterface::class, EloquentCrudRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(MenuRepositoryInterface::class, MenuRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        // The navigation snapshot caches role names. Renaming or deleting a role
        // would otherwise leave the cached gate referencing a stale name.
        $flushMenuCache = fn () => $this->app->make(MenuRepositoryInterface::class)->flushNavigationCache();
        Role::updated($flushMenuCache);
        Role::deleted($flushMenuCache);
    }
}
