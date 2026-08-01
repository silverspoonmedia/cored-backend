<?php

namespace App\Domain\Inference\Services;

use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Inference\Models\AiModelRateSnapshot;
use App\Domain\Inference\Models\InferenceProvider;
use App\Domain\Inference\Models\ProviderModelRate;
use App\Domain\Microstock\Services\UnknownModelBillingConsolidationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InferenceCatalogService
{
    public function __construct(
        private readonly InferenceRateService $rateService,
        private readonly UnknownModelBillingConsolidationService $consolidation,
    ) {}

    /**
     * @return Collection<int, AiModelCard>
     */
    public function listModelCards(): Collection
    {
        return AiModelCard::query()
            ->orderBy('slug')
            ->get();
    }

    public function getModelCard(string $id): AiModelCard
    {
        return AiModelCard::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createModelCard(array $data): AiModelCard
    {
        return AiModelCard::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateModelCard(string $id, array $data): AiModelCard
    {
        $card = $this->getModelCard($id);
        $card->update($data);

        return $card->fresh() ?? $card;
    }

    public function deleteModelCard(string $id): void
    {
        $card = $this->getModelCard($id);

        if (AiModelRateSnapshot::query()->where('model_card_id', $card->id)->exists()) {
            throw new HttpException(409, __('api/inference.model_cards.delete_has_snapshots'));
        }

        $card->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function providerRateForCard(AiModelCard $card, ?string $providerSlug = null): array
    {
        return $this->rateService->ratesForProviderModel($card->slug, $providerSlug);
    }

    /**
     * @return Collection<int, InferenceProvider>
     */
    public function listProviders(): Collection
    {
        return InferenceProvider::query()
            ->withCount('modelRates')
            ->orderBy('slug')
            ->get();
    }

    public function getProvider(string $id): InferenceProvider
    {
        return InferenceProvider::query()
            ->with([
                'modelRates.modelCard:id,slug,display_name,is_active',
            ])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createProvider(array $data): InferenceProvider
    {
        return InferenceProvider::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProvider(string $id, array $data): InferenceProvider
    {
        $provider = InferenceProvider::query()->findOrFail($id);
        $provider->update($data);

        return $this->getProvider($provider->id);
    }

    public function deleteProvider(string $id): void
    {
        InferenceProvider::query()->findOrFail($id)->delete();
    }

    /**
     * @param  list<array{model_card_id: string, input_per_million_usd: float|int|string, output_per_million_usd: float|int|string}>  $rates
     */
    public function syncProviderRates(string $providerId, array $rates): InferenceProvider
    {
        $provider = InferenceProvider::query()->findOrFail($providerId);

        DB::transaction(function () use ($provider, $rates): void {
            $incomingIds = collect($rates)
                ->pluck('model_card_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $cardsNeedingConsolidation = AiModelCard::query()
                ->whereIn('id', $incomingIds)
                ->where('needs_rate_classification', true)
                ->get()
                ->keyBy('id');

            ProviderModelRate::query()
                ->where('inference_provider_id', $provider->id)
                ->whereNotIn('model_card_id', $incomingIds)
                ->delete();

            foreach ($rates as $row) {
                ProviderModelRate::query()->updateOrCreate(
                    [
                        'inference_provider_id' => $provider->id,
                        'model_card_id' => $row['model_card_id'],
                    ],
                    [
                        'input_per_million_usd' => $row['input_per_million_usd'],
                        'output_per_million_usd' => $row['output_per_million_usd'],
                    ],
                );
            }

            foreach ($incomingIds as $cardId) {
                if (! $cardsNeedingConsolidation->has($cardId)) {
                    continue;
                }

                $this->consolidation->consolidate((string) $provider->id, (string) $cardId);

                $card = $cardsNeedingConsolidation->get($cardId);
                if ($card !== null) {
                    $card->forceFill(['needs_rate_classification' => false])->save();
                    Cache::forget('inference:unregistered_model_notify:'.$card->slug);
                }
            }
        });

        return $this->getProvider($provider->id);
    }
}
