<?php

namespace App\Providers\Domain;

use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Inference\Models\InferenceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InferenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::bind('model_card', fn (string $value) => AiModelCard::query()->findOrFail($value));
        Route::bind('provider', fn (string $value) => InferenceProvider::query()->findOrFail($value));
    }
}
