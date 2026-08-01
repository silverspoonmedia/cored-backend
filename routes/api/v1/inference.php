<?php

use App\Domain\Inference\Http\Controllers\API\V1\AiModelCardController;
use App\Domain\Inference\Http\Controllers\API\V1\InferenceProviderController;
use App\Domain\Inference\Http\Controllers\API\V1\InferenceUsageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('inference')->name('inference.')->group(function (): void {
    Route::apiResource('model-cards', AiModelCardController::class)
        ->parameters(['model-cards' => 'model_card']);

    Route::apiResource('providers', InferenceProviderController::class);

    Route::put('providers/{provider}/rates', [InferenceProviderController::class, 'syncRates'])
        ->middleware('permission:manage inference catalog')
        ->name('providers.rates.sync');

    Route::get('usage/overview', [InferenceUsageController::class, 'overview'])
        ->name('usage.overview');

    Route::get('usage-events', [InferenceUsageController::class, 'usageEvents'])
        ->name('usage-events.index');
});
