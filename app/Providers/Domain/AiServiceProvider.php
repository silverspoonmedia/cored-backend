<?php

namespace App\Providers\Domain;

use App\Domain\Ai\Contracts\AiConnectionProbe;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Policies\AiConnectionPolicy;
use App\Domain\Ai\Services\Probes\OpenAiCompatibleProbe;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiConnectionProbe::class, OpenAiCompatibleProbe::class);
    }

    public function boot(): void
    {
        Gate::policy(AiConnection::class, AiConnectionPolicy::class);

        Route::bind('connection', fn (string $value) => AiConnection::query()->findOrFail($value));
    }
}
