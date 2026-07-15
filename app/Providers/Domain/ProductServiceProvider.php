<?php

namespace App\Providers\Domain;

use App\Domain\Product\Models\Item;
use App\Domain\Product\Policies\ItemPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ProductServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Item::class, ItemPolicy::class);
    }
}
