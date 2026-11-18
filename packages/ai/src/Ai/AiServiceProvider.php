<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Console\Commands\ImportLegacyEnvCommand;
use App\Domain\Ai\Contracts\AiConnectionProbe;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Policies\AiConnectionPolicy;
use App\Domain\Ai\Services\Probes\OpenAiCompatibleProbe;
use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Inference\Models\InferenceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * One provider for `silverspoonmedia/ai` — two core domains, one package.
 *
 * `Inference` owns the model catalog and the per-provider rates; `Ai` owns the
 * endpoints and keys that reach a provider. A rate is meaningless without the
 * connection that bills it, so they ship as one Composer package while staying
 * two namespaces, two route files and two registry entries.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Config ship as defaults: a host that published its own copy keeps it.
        $this->mergeConfigFrom(__DIR__.'/../../config/ai.php', 'ai');
        $this->mergeConfigFrom(__DIR__.'/../../config/inference.php', 'inference');

        $this->app->bind(AiConnectionProbe::class, OpenAiCompatibleProbe::class);
    }

    public function boot(): void
    {
        Gate::policy(AiConnection::class, AiConnectionPolicy::class);

        Route::bind('connection', fn (string $value) => AiConnection::query()->findOrFail($value));
        Route::bind('model_card', fn (string $value) => AiModelCard::query()->findOrFail($value));
        Route::bind('provider', fn (string $value) => InferenceProvider::query()->findOrFail($value));

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Plain (non-namespaced) lang path: FileLoader::loadPaths() resolves
        // "{path}/{locale}/{group}.php", so the path is the parent of the locale
        // directories. Keys stay `api/ai.*` and `api/inference.*`.
        $this->app['translator']->getLoader()->addPath(__DIR__.'/../../resources/lang');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportLegacyEnvCommand::class,
            ]);
        }
    }
}
