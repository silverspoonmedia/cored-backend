<?php

namespace App\Domain\Inference\Services;

use App\Domain\Inference\Enums\InferenceRateSource;
use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Inference\Models\InferenceProvider;
use App\Domain\Inference\Models\ProviderModelRate;

class InferenceRateService
{
    /**
     * Resolve per-million USD rates for a resolved model slug on a provider.
     *
     * @return array{
     *     input_per_million_usd: float,
     *     output_per_million_usd: float,
     *     model_card_id: string|null,
     *     inference_provider_id: string|null,
     *     provider_slug: string|null,
     *     from_default: bool,
     *     rate_source: string
     * }
     */
    public function ratesForProviderModel(string $resolvedSlug, ?string $providerSlug = null): array
    {
        $providerSlug = $providerSlug ?? $this->defaultProviderSlug();
        $slug = trim($resolvedSlug) !== '' ? trim($resolvedSlug) : 'unknown';

        $provider = InferenceProvider::query()->where('slug', $providerSlug)->first();
        $card = AiModelCard::query()->where('slug', $slug)->first();

        if ($provider !== null && $card !== null) {
            $rate = ProviderModelRate::query()
                ->where('inference_provider_id', $provider->id)
                ->where('model_card_id', $card->id)
                ->first();

            if ($rate !== null) {
                return [
                    'input_per_million_usd' => (float) $rate->input_per_million_usd,
                    'output_per_million_usd' => (float) $rate->output_per_million_usd,
                    'model_card_id' => (string) $card->id,
                    'inference_provider_id' => (string) $provider->id,
                    'provider_slug' => $providerSlug,
                    'from_default' => false,
                    'rate_source' => InferenceRateSource::Known->value,
                ];
            }
        }

        $unknown = [
            'input_per_million_usd' => setting_config('inference.unknown_model_rates.input_per_million_usd'),
            'output_per_million_usd' => setting_config('inference.unknown_model_rates.output_per_million_usd'),
        ];
        $unknownInput = $unknown['input_per_million_usd'] ?? null;
        $unknownOutput = $unknown['output_per_million_usd'] ?? null;

        if ($unknownInput !== null && $unknownOutput !== null) {
            return [
                'input_per_million_usd' => (float) $unknownInput,
                'output_per_million_usd' => (float) $unknownOutput,
                'model_card_id' => $card !== null ? (string) $card->id : null,
                'inference_provider_id' => $provider !== null ? (string) $provider->id : null,
                'provider_slug' => $providerSlug,
                'from_default' => true,
                'rate_source' => InferenceRateSource::UnknownProvisional->value,
            ];
        }

        $default = [
            'input_per_million_usd' => setting_config('inference.default_rates.input_per_million_usd', 5.0),
            'output_per_million_usd' => setting_config('inference.default_rates.output_per_million_usd', 20.0),
        ];

        return [
            'input_per_million_usd' => (float) ($default['input_per_million_usd'] ?? 5.0),
            'output_per_million_usd' => (float) ($default['output_per_million_usd'] ?? 20.0),
            'model_card_id' => $card !== null ? (string) $card->id : null,
            'inference_provider_id' => $provider !== null ? (string) $provider->id : null,
            'provider_slug' => $providerSlug,
            'from_default' => true,
            'rate_source' => InferenceRateSource::ConfigDefault->value,
        ];
    }

    public function defaultProviderSlug(): string
    {
        return (string) setting_config('inference.default_provider_slug', 'openwebui-9router');
    }

    public function findProviderBySlug(string $slug): ?InferenceProvider
    {
        return InferenceProvider::query()->where('slug', $slug)->first();
    }

    public function isRegisteredOnProvider(string $resolvedSlug, ?string $providerSlug = null): bool
    {
        $providerSlug = $providerSlug ?? $this->defaultProviderSlug();
        $slug = trim($resolvedSlug) !== '' ? trim($resolvedSlug) : 'unknown';

        $provider = InferenceProvider::query()->where('slug', $providerSlug)->first();
        if ($provider === null) {
            return false;
        }

        $card = AiModelCard::query()->where('slug', $slug)->first();
        if ($card === null) {
            return false;
        }

        return ProviderModelRate::query()
            ->where('inference_provider_id', $provider->id)
            ->where('model_card_id', $card->id)
            ->exists();
    }

    /**
     * Ensure a model card exists for snapshot FK (auto-create for unknown resolved slugs).
     */
    public function ensureModelCard(string $slug, bool $markNeedsClassification = false): AiModelCard
    {
        $slug = trim($slug) !== '' ? trim($slug) : 'unknown';

        $card = AiModelCard::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'display_name' => $slug,
                'is_active' => true,
                'needs_rate_classification' => $markNeedsClassification,
            ],
        );

        if ($markNeedsClassification && ! $card->needs_rate_classification) {
            $card->forceFill(['needs_rate_classification' => true])->save();
        }

        return $card->fresh() ?? $card;
    }
}
