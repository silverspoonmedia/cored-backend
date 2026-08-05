<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Inference\Enums\InferenceRateSource;
use App\Domain\Inference\Services\AiModelRateSnapshotService;
use App\Domain\Inference\Services\InferenceRateService;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\BillingSource;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Support\AnalysisBillingContext;
use App\Domain\Microstock\Support\AnalysisBillingPlan;
use App\Domain\Notification\Events\ActivityNotificationEvent;
use App\Domain\Notification\Notifications\NotificationType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiBillingService
{
    public function __construct(
        private readonly AiCreditService $credits,
        private readonly AiDailyFreeService $dailyFree,
        private readonly InferenceRateService $inferenceRates,
        private readonly AiModelRateSnapshotService $rateSnapshots,
    ) {}

    public function estimateForAssets(int $count): int
    {
        $perAsset = (int) setting_config('microstock.ai_billing.estimated_billable_micro_usd_per_asset', 150_000);
        $factor = (float) setting_config('microstock.ai_billing.estimate_thinking_attempt_factor', 1.5);

        return max(0, $count) * (int) round($perAsset * $factor);
    }

    public function assertCanAnalyze(User $user, int $assetCount): void
    {
        $this->planAnalyze($user, $assetCount);
    }

    public function planAnalyze(User $user, int $assetCount, ?string $forcedMode = null): AnalysisBillingPlan
    {
        return DB::transaction(function () use ($user, $assetCount, $forcedMode): AnalysisBillingPlan {
            $userId = (string) $user->id;

            if ($forcedMode === 'paid') {
                $balance = $this->credits->balance($userId);
                if ($balance < 0) {
                    throw new MicrostockException(
                        __('api/microstock.insufficient_ai_credits'),
                        422,
                        ['daily_free' => $this->dailyFree->snapshot($userId)],
                    );
                }

                $required = $this->estimateForAssets($assetCount);
                if ($balance < $required) {
                    throw new MicrostockException(
                        __('api/microstock.insufficient_ai_credits'),
                        422,
                        ['daily_free' => $this->dailyFree->snapshot($userId)],
                    );
                }

                return new AnalysisBillingPlan(0, $assetCount);
            }

            $split = $this->dailyFree->allocateInTransaction($userId, $assetCount);

            if ($split['prepaid'] > 0) {
                $balance = $this->credits->balance($userId);
                if ($balance < 0) {
                    throw new MicrostockException(
                        __('api/microstock.insufficient_ai_credits'),
                        422,
                        ['daily_free' => $this->dailyFree->snapshot($userId)],
                    );
                }

                $required = $this->estimateForAssets($split['prepaid']);
                if ($balance < $required) {
                    throw new MicrostockException(
                        __('api/microstock.insufficient_ai_credits'),
                        422,
                        ['daily_free' => $this->dailyFree->snapshot($userId)],
                    );
                }
            }

            return new AnalysisBillingPlan($split['daily'], $split['prepaid']);
        });
    }

    /**
     * @param  array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}|null  $usage
     */
    public function recordUsage(
        string $stage,
        string $aliasModel,
        ?array $usage,
        ?string $resolvedModel = null,
        ?int $attemptIndex = null,
        ?string $providerRequestId = null,
        ?string $inferenceProviderSlug = null,
    ): void {
        $context = AnalysisBillingContext::current();
        if ($context === null) {
            return;
        }

        if ($usage === null) {
            Log::warning('microstock.billing.missing_usage', [
                'stage' => $stage,
                'alias_model' => $aliasModel,
                'metadata_id' => $context->metadataId,
                'analysis_run_id' => $context->analysisRunId,
            ]);
        }

        $prompt = max(0, (int) ($usage['prompt_tokens'] ?? 0));
        $completion = max(0, (int) ($usage['completion_tokens'] ?? 0));
        $total = max(0, (int) ($usage['total_tokens'] ?? ($prompt + $completion)));
        $resolved = $resolvedModel ?: 'unknown';
        $providerSlug = $inferenceProviderSlug ?? $this->inferenceRates->defaultProviderSlug();

        if (! $this->inferenceRates->isRegisteredOnProvider($resolved, $providerSlug)) {
            $card = $this->inferenceRates->ensureModelCard($resolved, markNeedsClassification: true);
            $this->notifyUnregisteredModelIfNeeded($resolved, $providerSlug, $card->id);
        }

        $snapshot = $this->rateSnapshots->capture($resolved, $providerSlug);
        $costs = $this->costsForTokens($resolved, $prompt, $completion, $providerSlug);
        $inferenceProviderId = $snapshot->inference_provider_id;

        if ($context->batchTargets !== []) {
            $this->recordSplitBatch(
                $context,
                $stage,
                $aliasModel,
                $resolved,
                $prompt,
                $completion,
                $total,
                $costs,
                $attemptIndex,
                $providerRequestId,
                (string) $snapshot->id,
                $inferenceProviderId,
            );

            return;
        }

        AiUsageEvent::query()->create([
            'user_id' => $context->billingUserId,
            'asset_metadata_id' => $context->metadataId,
            'file_id' => $context->fileId,
            'folder_id' => $context->folderId,
            'analysis_run_id' => $context->analysisRunId,
            'billing_source' => $context->billingSource->value,
            'stage' => $stage,
            'attempt_index' => $attemptIndex,
            'alias_model' => $aliasModel,
            'resolved_model' => $resolved,
            'ai_model_rate_snapshot_id' => $snapshot->id,
            'inference_provider_id' => $inferenceProviderId,
            'provider_request_id' => $providerRequestId,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'provider_cost_micro_usd' => $costs['provider'],
            'billable_micro_usd' => $costs['billable'],
        ]);
    }

    /**
     * @param  array{provider: int, billable: int}  $costs
     */
    private function recordSplitBatch(
        AnalysisBillingContext $context,
        string $stage,
        string $aliasModel,
        string $resolved,
        int $prompt,
        int $completion,
        int $total,
        array $costs,
        ?int $attemptIndex,
        ?string $providerRequestId,
        string $rateSnapshotId,
        ?string $inferenceProviderId = null,
    ): void {
        $targets = $context->batchTargets;
        $count = max(1, count($targets));
        $promptShare = intdiv($prompt, $count);
        $completionShare = intdiv($completion, $count);
        $totalShare = intdiv($total, $count);
        $providerShare = intdiv($costs['provider'], $count);
        $billableShare = intdiv($costs['billable'], $count);

        foreach ($targets as $target) {
            AiUsageEvent::query()->create([
                'user_id' => $context->billingUserId,
                'asset_metadata_id' => $target['metadata_id'],
                'file_id' => $target['file_id'],
                'folder_id' => $context->folderId,
                'analysis_run_id' => $context->analysisRunId,
                'billing_source' => $context->billingSource->value,
                'stage' => $stage,
                'attempt_index' => $attemptIndex,
                'alias_model' => $aliasModel,
                'resolved_model' => $resolved,
                'ai_model_rate_snapshot_id' => $rateSnapshotId,
                'inference_provider_id' => $inferenceProviderId,
                'provider_request_id' => $providerRequestId,
                'prompt_tokens' => $promptShare,
                'completion_tokens' => $completionShare,
                'total_tokens' => $totalShare,
                'provider_cost_micro_usd' => $providerShare,
                'billable_micro_usd' => $billableShare,
            ]);
        }
    }

    public function settleSuccessfulAnalysis(AssetMetadata $metadata): void
    {
        $billing = $metadata->ai_billing;
        if (is_array($billing) && ! ($billing['partial'] ?? false)) {
            return;
        }

        $this->settleUsageEvents($metadata, stages: null, partial: false);
    }

    public function settlePartialVisionUsage(AssetMetadata $metadata): void
    {
        $visionStatus = $metadata->ai_vision['status'] ?? AnalysisStageStatus::Pending->value;
        if ($visionStatus !== AnalysisStageStatus::Ok->value) {
            return;
        }

        if (is_array($metadata->ai_billing) && ($metadata->ai_billing['partial'] ?? false) === true) {
            return;
        }

        if (is_array($metadata->ai_billing) && ! ($metadata->ai_billing['partial'] ?? false)) {
            return;
        }

        $this->settleUsageEvents($metadata, stages: ['vision'], partial: true);
    }

    /**
     * @param  list<string>|null  $stages  null = all stages for the current run
     */
    private function settleUsageEvents(AssetMetadata $metadata, ?array $stages, bool $partial): void
    {
        DB::transaction(function () use ($metadata, $stages, $partial): void {
            $locked = AssetMetadata::query()->lockForUpdate()->find($metadata->id);
            if ($locked === null) {
                return;
            }

            if ($partial && is_array($locked->ai_billing) && ($locked->ai_billing['partial'] ?? false)) {
                return;
            }

            if (! $partial && is_array($locked->ai_billing) && ! ($locked->ai_billing['partial'] ?? false)) {
                return;
            }

            $query = AiUsageEvent::query()
                ->where('asset_metadata_id', $locked->id)
                ->whereNull('billed_at')
                ->whereNull('voided_at');

            if ($locked->analysis_run_id !== null) {
                $query->where('analysis_run_id', $locked->analysis_run_id);
            }

            if ($stages !== null) {
                $query->whereIn('stage', $stages);
            }

            $events = $query->lockForUpdate()->get();

            if ($events->isEmpty()) {
                return;
            }

            $billable = (int) $events->sum('billable_micro_usd');
            $provider = (int) $events->sum('provider_cost_micro_usd');
            $tokens = (int) $events->sum('total_tokens');
            $models = $events->pluck('resolved_model')->unique()->values()->all();
            $billingUserId = (string) ($locked->billing_user_id ?? $locked->user_id);
            $billingSource = BillingSource::tryFrom((string) ($locked->billing_source ?? ''))
                ?? BillingSource::Prepaid;
            $userBillable = $billingSource === BillingSource::Prepaid ? $billable : 0;

            if ($userBillable > 0) {
                $this->credits->debit(
                    $billingUserId,
                    $userBillable,
                    'asset_metadata',
                    (string) $locked->id,
                );
            }

            $billedAt = now();
            AiUsageEvent::query()
                ->whereIn('id', $events->pluck('id'))
                ->update(['billed_at' => $billedAt]);

            if ($partial) {
                $existing = is_array($locked->ai_billing) ? $locked->ai_billing : [];
                $locked->forceFill([
                    'ai_billing' => [
                        'partial' => true,
                        'source' => $billingSource->value,
                        'stages' => $stages ?? [],
                        'resolved_models' => array_values(array_unique(array_merge(
                            $existing['resolved_models'] ?? [],
                            $models,
                        ))),
                        'total_tokens' => (int) ($existing['total_tokens'] ?? 0) + $tokens,
                        'provider_cost_micro_usd' => (int) ($existing['provider_cost_micro_usd'] ?? 0) + $provider,
                        'billable_micro_usd' => (int) ($existing['billable_micro_usd'] ?? 0) + $userBillable,
                        'billed_at' => $billedAt->toIso8601String(),
                    ],
                ])->save();

                return;
            }

            $existing = is_array($locked->ai_billing) && ($locked->ai_billing['partial'] ?? false)
                ? $locked->ai_billing
                : [];

            $locked->forceFill([
                'ai_billing' => [
                    'partial' => false,
                    'source' => $billingSource->value,
                    'resolved_models' => array_values(array_unique(array_merge(
                        $existing['resolved_models'] ?? [],
                        $models,
                    ))),
                    'total_tokens' => (int) ($existing['total_tokens'] ?? 0) + $tokens,
                    'provider_cost_micro_usd' => (int) ($existing['provider_cost_micro_usd'] ?? 0) + $provider,
                    'billable_micro_usd' => (int) ($existing['billable_micro_usd'] ?? 0) + $userBillable,
                    'billed_at' => $billedAt->toIso8601String(),
                ],
            ])->save();
        });
    }

    /** @return array{provider: int, billable: int} */
    public function costsForTokens(
        string $resolvedModel,
        int $promptTokens,
        int $completionTokens,
        ?string $providerSlug = null,
    ): array {
        $rates = $this->inferenceRates->ratesForProviderModel($resolvedModel, $providerSlug);
        $provider = (int) round(
            ($promptTokens / 1_000_000) * $rates['input_per_million_usd'] * 1_000_000
            + ($completionTokens / 1_000_000) * $rates['output_per_million_usd'] * 1_000_000
        );
        $markup = (float) setting_config('microstock.ai_billing.markup_multiplier', 1.35);
        $billable = (int) round($provider * $markup);

        return [
            'provider' => max(0, $provider),
            'billable' => max(0, $billable),
        ];
    }

    public function microUsdToUsd(int $microUsd): float
    {
        return round($microUsd / 1_000_000, 6);
    }

    private function notifyUnregisteredModelIfNeeded(string $resolvedSlug, string $providerSlug, string $modelCardId): void
    {
        $cacheKey = 'inference:unregistered_model_notify:'.$resolvedSlug;

        // Forever dedup flag (null TTL → Cache::forever). Forget when classified.
        if (! Cache::add($cacheKey, true)) {
            return;
        }

        $rates = $this->inferenceRates->ratesForProviderModel($resolvedSlug, $providerSlug);
        if (($rates['rate_source'] ?? '') !== InferenceRateSource::UnknownProvisional->value) {
            Cache::forget($cacheKey);

            return;
        }

        ActivityNotificationEvent::dispatch(
            NotificationType::InferenceUnregisteredModel,
            [
                'resolved_model' => $resolvedSlug,
                'provider_slug' => $providerSlug,
                'input_per_million_usd' => $rates['input_per_million_usd'],
                'output_per_million_usd' => $rates['output_per_million_usd'],
            ],
            roles: ['Root', 'Admin', 'Management'],
            actionRoute: 'inference.model-cards.show',
            actionParams: ['modelCard' => $modelCardId],
        );
    }
}
