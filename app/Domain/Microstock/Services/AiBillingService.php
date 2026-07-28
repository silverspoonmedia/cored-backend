<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Support\AnalysisBillingContext;
use Illuminate\Support\Facades\DB;

class AiBillingService
{
    public function __construct(
        private readonly AiCreditService $credits,
    ) {}

    public function estimateForAssets(int $count): int
    {
        $perAsset = (int) config('microstock.ai_billing.estimated_billable_micro_usd_per_asset', 150_000);

        return max(0, $count) * $perAsset;
    }

    public function assertCanAnalyze(User $user, int $assetCount): void
    {
        $required = $this->estimateForAssets($assetCount);
        if ($this->credits->balance((string) $user->id) < $required) {
            throw new MicrostockException(__('api/microstock.insufficient_ai_credits'), 422);
        }
    }

    /**
     * @param  array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}|null  $usage
     */
    public function recordUsage(
        string $stage,
        string $aliasModel,
        ?array $usage,
        ?string $resolvedModel = null,
    ): void {
        $context = AnalysisBillingContext::current();
        if ($context === null) {
            return;
        }

        $prompt = max(0, (int) ($usage['prompt_tokens'] ?? 0));
        $completion = max(0, (int) ($usage['completion_tokens'] ?? 0));
        $total = max(0, (int) ($usage['total_tokens'] ?? ($prompt + $completion)));
        $resolved = $resolvedModel ?: 'unknown';
        $costs = $this->costsForTokens($resolved, $prompt, $completion);

        if ($context->batchTargets !== []) {
            $this->recordSplitBatch($context, $stage, $aliasModel, $resolved, $prompt, $completion, $total, $costs);

            return;
        }

        AiUsageEvent::query()->create([
            'user_id' => $context->userId,
            'asset_metadata_id' => $context->metadataId,
            'file_id' => $context->fileId,
            'folder_id' => $context->folderId,
            'stage' => $stage,
            'alias_model' => $aliasModel,
            'resolved_model' => $resolved,
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
                'user_id' => $context->userId,
                'asset_metadata_id' => $target['metadata_id'],
                'file_id' => $target['file_id'],
                'folder_id' => $context->folderId,
                'stage' => $stage,
                'alias_model' => $aliasModel,
                'resolved_model' => $resolved,
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
        if ($metadata->ai_billing !== null) {
            return;
        }

        DB::transaction(function () use ($metadata): void {
            $events = AiUsageEvent::query()
                ->where('asset_metadata_id', $metadata->id)
                ->whereNull('billed_at')
                ->lockForUpdate()
                ->get();

            if ($events->isEmpty()) {
                return;
            }

            $billable = (int) $events->sum('billable_micro_usd');
            $provider = (int) $events->sum('provider_cost_micro_usd');
            $tokens = (int) $events->sum('total_tokens');
            $models = $events->pluck('resolved_model')->unique()->values()->all();

            if ($billable > 0) {
                $this->credits->debit(
                    (string) $metadata->user_id,
                    $billable,
                    'asset_metadata',
                    (string) $metadata->id,
                );
            }

            $billedAt = now();
            AiUsageEvent::query()
                ->whereIn('id', $events->pluck('id'))
                ->update(['billed_at' => $billedAt]);

            $metadata->forceFill([
                'ai_billing' => [
                    'resolved_models' => $models,
                    'total_tokens' => $tokens,
                    'provider_cost_micro_usd' => $provider,
                    'billable_micro_usd' => $billable,
                    'billed_at' => $billedAt->toIso8601String(),
                ],
            ])->save();
        });
    }

    /** @return array{provider: int, billable: int} */
    public function costsForTokens(string $resolvedModel, int $promptTokens, int $completionTokens): array
    {
        $rates = $this->ratesForModel($resolvedModel);
        $provider = (int) round(
            ($promptTokens / 1_000_000) * $rates['input_per_million_usd'] * 1_000_000
            + ($completionTokens / 1_000_000) * $rates['output_per_million_usd'] * 1_000_000
        );
        $markup = (float) config('microstock.ai_billing.markup_multiplier', 1.35);
        $billable = (int) round($provider * $markup);

        return [
            'provider' => max(0, $provider),
            'billable' => max(0, $billable),
        ];
    }

    /** @return array{input_per_million_usd: float, output_per_million_usd: float} */
    private function ratesForModel(string $resolvedModel): array
    {
        $models = config('microstock.ai_billing.models', []);
        $rates = $models[$resolvedModel] ?? $models['default'] ?? [
            'input_per_million_usd' => 5.0,
            'output_per_million_usd' => 20.0,
        ];

        return [
            'input_per_million_usd' => (float) ($rates['input_per_million_usd'] ?? 5.0),
            'output_per_million_usd' => (float) ($rates['output_per_million_usd'] ?? 20.0),
        ];
    }

    public function microUsdToUsd(int $microUsd): float
    {
        return round($microUsd / 1_000_000, 6);
    }
}
