<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Inference\Enums\InferenceRateSource;
use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Inference\Models\InferenceProvider;
use App\Domain\Microstock\Enums\AiCreditTransactionType;
use App\Domain\Microstock\Enums\BillingSource;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Models\AssetMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnknownModelBillingConsolidationService
{
    public function __construct(
        private readonly AiBillingService $billing,
        private readonly AiCreditService $credits,
    ) {}

    /**
     * Recalculate settled usage that was billed at provisional rates after admin curates catalog rates.
     *
     * @return array{events_adjusted: int, net_adjustment_micro_usd: int}
     */
    public function consolidate(string $providerId, string $modelCardId): array
    {
        $card = AiModelCard::query()->findOrFail($modelCardId);
        $provider = InferenceProvider::query()->findOrFail($providerId);
        $providerSlug = $provider->slug;
        $batchId = (string) Str::uuid();
        $eventsAdjusted = 0;
        $netAdjustment = 0;

        DB::transaction(function () use ($providerId, $card, $providerSlug, $batchId, &$eventsAdjusted, &$netAdjustment): void {
            $events = AiUsageEvent::query()
                ->where('inference_provider_id', $providerId)
                ->where('resolved_model', $card->slug)
                ->whereNotNull('billed_at')
                ->whereNull('consolidated_at')
                ->whereNull('voided_at')
                ->whereHas('aiModelRateSnapshot', function ($query): void {
                    $query->where('rate_source', InferenceRateSource::UnknownProvisional->value);
                })
                ->lockForUpdate()
                ->get();

            if ($events->isEmpty()) {
                return;
            }

            $metadataAdjustments = [];
            $metadataProviderAdjustments = [];

            foreach ($events as $event) {
                $newCosts = $this->billing->costsForTokens(
                    $card->slug,
                    (int) $event->prompt_tokens,
                    (int) $event->completion_tokens,
                    $providerSlug,
                );

                $oldBillable = (int) $event->billable_micro_usd;
                $oldProvider = (int) $event->provider_cost_micro_usd;
                $newBillable = $newCosts['billable'];
                $newProvider = $newCosts['provider'];
                $billableDelta = $newBillable - $oldBillable;
                $providerDelta = $newProvider - $oldProvider;
                $billingSource = BillingSource::tryFrom((string) $event->billing_source) ?? BillingSource::Prepaid;

                if ($billingSource === BillingSource::Prepaid && $billableDelta !== 0) {
                    $userId = (string) $event->user_id;
                    $referenceId = $batchId.':'.$event->id;

                    if ($billableDelta < 0) {
                        $this->credits->addCredit(
                            $userId,
                            abs($billableDelta),
                            AiCreditTransactionType::Adjustment,
                            'model_card_consolidation',
                            $referenceId,
                        );
                    } else {
                        $this->credits->debit(
                            $userId,
                            $billableDelta,
                            'model_card_consolidation',
                            $referenceId,
                            AiCreditTransactionType::Adjustment,
                        );
                    }

                    $netAdjustment += $billableDelta;
                }

                $event->forceFill([
                    'billable_micro_usd' => $newBillable,
                    'provider_cost_micro_usd' => $newProvider,
                    'consolidated_at' => now(),
                    'consolidation_adjustment_micro_usd' => $billableDelta !== 0 ? $billableDelta : null,
                ])->save();

                if ($event->asset_metadata_id !== null && $billingSource === BillingSource::Prepaid) {
                    $metadataId = (string) $event->asset_metadata_id;
                    if ($billableDelta !== 0) {
                        $metadataAdjustments[$metadataId] = ($metadataAdjustments[$metadataId] ?? 0) + $billableDelta;
                    }
                    if ($providerDelta !== 0) {
                        $metadataProviderAdjustments[$metadataId] = ($metadataProviderAdjustments[$metadataId] ?? 0) + $providerDelta;
                    }
                }

                $eventsAdjusted++;
            }

            foreach ($metadataAdjustments as $metadataId => $adjustment) {
                $metadata = AssetMetadata::query()->lockForUpdate()->find($metadataId);
                if ($metadata === null || ! is_array($metadata->ai_billing)) {
                    continue;
                }

                $billing = $metadata->ai_billing;
                $providerAdjustment = $metadataProviderAdjustments[$metadataId] ?? 0;
                $metadata->forceFill([
                    'ai_billing' => [
                        ...$billing,
                        'billable_micro_usd' => max(0, (int) ($billing['billable_micro_usd'] ?? 0) + $adjustment),
                        'provider_cost_micro_usd' => (int) ($billing['provider_cost_micro_usd'] ?? 0) + $providerAdjustment,
                    ],
                ])->save();
            }
        });

        return [
            'events_adjusted' => $eventsAdjusted,
            'net_adjustment_micro_usd' => $netAdjustment,
        ];
    }
}
