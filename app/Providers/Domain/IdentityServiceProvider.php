<?php

namespace App\Providers\Domain;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\UserPolicy;
use App\Domain\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Domain\Identity\Repositories\Eloquent\UserRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
    }
}
