<?php

use App\Domain\Inference\Http\Controllers\Web\AiModelCardController;
use App\Domain\Inference\Http\Controllers\Web\InferenceProviderController;
use App\Domain\Inference\Http\Controllers\Web\InferenceUsageController;
use Illuminate\Support\Facades\Route;

Route::prefix('inference')->name('inference.')->group(function (): void {
    Route::get('model-cards/datatable', [AiModelCardController::class, 'datatable'])->name('model-cards.datatable');
    Route::get('model-cards/create', [AiModelCardController::class, 'create'])->name('model-cards.create');
    Route::post('model-cards', [AiModelCardController::class, 'store'])->name('model-cards.store');
    Route::get('model-cards/{modelCard}/edit', [AiModelCardController::class, 'edit'])->name('model-cards.edit');
    Route::put('model-cards/{modelCard}', [AiModelCardController::class, 'update'])->name('model-cards.update');
    Route::delete('model-cards/{modelCard}', [AiModelCardController::class, 'destroy'])->name('model-cards.destroy');
    Route::get('model-cards/{modelCard}', [AiModelCardController::class, 'show'])->name('model-cards.show');
    Route::get('model-cards', [AiModelCardController::class, 'index'])->name('model-cards.index');

    Route::get('providers/datatable', [InferenceProviderController::class, 'datatable'])->name('providers.datatable');
    Route::get('providers/create', [InferenceProviderController::class, 'create'])->name('providers.create');
    Route::post('providers', [InferenceProviderController::class, 'store'])->name('providers.store');
    Route::put('providers/{provider}/rates', [InferenceProviderController::class, 'syncRates'])->name('providers.sync-rates');
    Route::get('providers/{provider}/edit', [InferenceProviderController::class, 'edit'])->name('providers.edit');
    Route::put('providers/{provider}', [InferenceProviderController::class, 'update'])->name('providers.update');
    Route::delete('providers/{provider}', [InferenceProviderController::class, 'destroy'])->name('providers.destroy');
    Route::get('providers/{provider}', [InferenceProviderController::class, 'show'])->name('providers.show');
    Route::get('providers', [InferenceProviderController::class, 'index'])->name('providers.index');

    Route::get('usage/overview', [InferenceUsageController::class, 'overview'])->name('usage.overview');
    Route::get('usage-events/api', [InferenceUsageController::class, 'usageEvents'])->name('usage-events.api');
    Route::get('usage-events', [InferenceUsageController::class, 'index'])->name('usage-events.index');
});
