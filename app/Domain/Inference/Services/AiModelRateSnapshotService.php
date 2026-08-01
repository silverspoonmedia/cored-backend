<?php

namespace App\Domain\Inference\Services;

use App\Domain\Inference\Enums\InferenceRateSource;
use App\Domain\Inference\Models\AiModelRateSnapshot;
use App\Domain\Inference\Models\InferenceProvider;

class AiModelRateSnapshotService
{
    public function __construct(
        private readonly InferenceRateService $rates,
    ) {}

    public function capture(string $resolvedSlug, ?string $providerSlug = null): AiModelRateSnapshot
    {
        $providerSlug = $providerSlug ?? $this->rates->defaultProviderSlug();
        $rateData = $this->rates->ratesForProviderModel($resolvedSlug, $providerSlug);

        $card = $this->rates->ensureModelCard($resolvedSlug);

        $providerId = $rateData['inference_provider_id'];
        if ($providerId === null) {
            $provider = InferenceProvider::query()->where('slug', $providerSlug)->first();
            $providerId = $provider !== null ? (string) $provider->id : null;
        }

        if ($providerId === null) {
            throw new \RuntimeException("Inference provider [{$providerSlug}] is not seeded.");
        }

        $rateSource = $rateData['rate_source'] ?? InferenceRateSource::Known->value;

        $snapshot = AiModelRateSnapshot::query()->firstOrCreate(
            [
                'inference_provider_id' => $providerId,
                'model_slug' => $card->slug,
                'input_per_million_usd' => $rateData['input_per_million_usd'],
                'output_per_million_usd' => $rateData['output_per_million_usd'],
            ],
            [
                'model_card_id' => $card->id,
                'captured_at' => now(),
                'rate_source' => $rateSource,
            ],
        );

        if ($snapshot->rate_source !== $rateSource) {
            $snapshot->forceFill(['rate_source' => $rateSource])->save();
        }

        return $snapshot;
    }
}
