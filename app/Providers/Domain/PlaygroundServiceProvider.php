<?php

namespace App\Providers\Domain;

use App\Domain\Playground\Repositories\Contracts\OrderRepositoryInterface;
use App\Domain\Playground\Repositories\Eloquent\OrderRepository;
use Illuminate\Support\ServiceProvider;

class PlaygroundServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
    }
}
