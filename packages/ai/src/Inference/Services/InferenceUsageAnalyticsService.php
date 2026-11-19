<?php

namespace App\Domain\Inference\Services;

use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Inference\Models\InferenceProvider;
use App\Support\Ai\Contracts\AiUsageLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class InferenceUsageAnalyticsService
{
    public function __construct(
        private readonly AiUsageLedger $ledger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(
        ?string $from = null,
        ?string $to = null,
        ?string $modelSlug = null,
        ?string $modelCardId = null,
        ?string $userId = null,
        ?string $stage = null,
        ?string $inferenceProviderId = null,
    ): array {
        $eventsQuery = $this->usageEventsQuery($from, $to, $modelSlug, $modelCardId, $userId, $stage, $inferenceProviderId);

        $totalsRow = (clone $eventsQuery)->selectRaw('
            COUNT(*) as event_count,
            COUNT(DISTINCT user_id) as unique_users,
            COALESCE(SUM(total_tokens), 0) as total_tokens,
            COALESCE(SUM(provider_cost_micro_usd), 0) as provider_cost_micro_usd,
            COALESCE(SUM(billable_micro_usd), 0) as billable_micro_usd
        ')->first();

        $providerMicro = (int) ($totalsRow?->getAttribute('provider_cost_micro_usd') ?? 0);
        $billableMicro = (int) ($totalsRow?->getAttribute('billable_micro_usd') ?? 0);

        $bySource = (clone $eventsQuery)
            ->selectRaw('
                billing_source,
                COUNT(*) as event_count,
                COALESCE(SUM(provider_cost_micro_usd), 0) as provider_cost_micro_usd,
                COALESCE(SUM(billable_micro_usd), 0) as billable_micro_usd
            ')
            ->groupBy('billing_source')
            ->get()
            ->mapWithKeys(function (Model $row): array {
                $source = (string) ($row->getAttribute('billing_source') ?? 'unknown');

                return [$source => [
                    'event_count' => (int) $row->getAttribute('event_count'),
                    'provider_cost_usd' => $this->ledger->formatUsd((int) $row->getAttribute('provider_cost_micro_usd')),
                    'billable_usd' => $this->ledger->formatUsd((int) $row->getAttribute('billable_micro_usd')),
                ]];
            })
            ->all();

        $byStage = (clone $eventsQuery)
            ->selectRaw('
                stage,
                COUNT(*) as event_count,
                COALESCE(SUM(provider_cost_micro_usd), 0) as provider_cost_micro_usd,
                COALESCE(SUM(billable_micro_usd), 0) as billable_micro_usd
            ')
            ->groupBy('stage')
            ->orderByDesc('event_count')
            ->get()
            ->map(fn (Model $row): array => [
                'stage' => (string) ($row->getAttribute('stage') ?? ''),
                'event_count' => (int) $row->getAttribute('event_count'),
                'provider_cost_usd' => $this->ledger->formatUsd((int) $row->getAttribute('provider_cost_micro_usd')),
                'billable_usd' => $this->ledger->formatUsd((int) $row->getAttribute('billable_micro_usd')),
            ])
            ->values()
            ->all();

        $byModel = (clone $eventsQuery)
            ->selectRaw('
                COALESCE(NULLIF(resolved_model, \'\'), alias_model, \'unknown\') as model_name,
                COUNT(*) as event_count,
                COALESCE(SUM(total_tokens), 0) as total_tokens,
                COALESCE(SUM(provider_cost_micro_usd), 0) as provider_cost_micro_usd,
                COALESCE(SUM(billable_micro_usd), 0) as billable_micro_usd
            ')
            ->groupBy('model_name')
            ->orderByDesc('event_count')
            ->limit(20)
            ->get()
            ->map(fn (Model $row): array => [
                'resolved_model' => (string) ($row->getAttribute('model_name') ?? ''),
                'event_count' => (int) $row->getAttribute('event_count'),
                'total_tokens' => (int) $row->getAttribute('total_tokens'),
                'provider_cost_usd' => $this->ledger->formatUsd((int) $row->getAttribute('provider_cost_micro_usd')),
                'billable_usd' => $this->ledger->formatUsd((int) $row->getAttribute('billable_micro_usd')),
            ])
            ->values()
            ->all();

        $byProvider = (clone $eventsQuery)
            ->selectRaw('
                inference_provider_id,
                COUNT(*) as event_count,
                COALESCE(SUM(total_tokens), 0) as total_tokens,
                COALESCE(SUM(provider_cost_micro_usd), 0) as provider_cost_micro_usd,
                COALESCE(SUM(billable_micro_usd), 0) as billable_micro_usd
            ')
            ->whereNotNull('inference_provider_id')
            ->groupBy('inference_provider_id')
            ->orderByDesc('event_count')
            ->get();

        $providerSlugs = InferenceProvider::query()
            ->whereIn('id', $byProvider->pluck('inference_provider_id')->filter()->all())
            ->pluck('slug', 'id');

        $byProviderRows = $byProvider
            ->map(fn (Model $row): array => [
                'inference_provider_id' => (string) $row->getAttribute('inference_provider_id'),
                'provider_slug' => $providerSlugs[(string) $row->getAttribute('inference_provider_id')] ?? null,
                'event_count' => (int) $row->getAttribute('event_count'),
                'total_tokens' => (int) $row->getAttribute('total_tokens'),
                'provider_cost_usd' => $this->ledger->formatUsd((int) $row->getAttribute('provider_cost_micro_usd')),
                'billable_usd' => $this->ledger->formatUsd((int) $row->getAttribute('billable_micro_usd')),
            ])
            ->values()
            ->all();

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'totals' => [
                'event_count' => (int) ($totalsRow?->getAttribute('event_count') ?? 0),
                'unique_users' => (int) ($totalsRow?->getAttribute('unique_users') ?? 0),
                'total_tokens' => (int) ($totalsRow?->getAttribute('total_tokens') ?? 0),
                'provider_cost_micro_usd' => $providerMicro,
                'provider_cost_usd' => $this->ledger->formatUsd($providerMicro),
                'billable_micro_usd' => $billableMicro,
                'billable_usd' => $this->ledger->formatUsd($billableMicro),
                'margin_micro_usd' => max(0, $billableMicro - $providerMicro),
                'margin_usd' => $this->ledger->formatUsd(max(0, $billableMicro - $providerMicro)),
            ],
            'by_billing_source' => $bySource,
            'by_stage' => $byStage,
            'by_model' => $byModel,
            'by_provider' => $byProviderRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function modelCardSummary(
        string $cardId,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $card = AiModelCard::query()->findOrFail($cardId);

        if ($from === null && $to === null) {
            $from = Carbon::now()->subDays(30)->toDateString();
        }

        $eventsQuery = $this->usageEventsQuery($from, $to, $card->slug, $card->id);

        $totalsRow = (clone $eventsQuery)->selectRaw('
            COUNT(*) as event_count,
            COALESCE(SUM(total_tokens), 0) as total_tokens,
            COALESCE(SUM(provider_cost_micro_usd), 0) as provider_cost_micro_usd,
            COALESCE(SUM(billable_micro_usd), 0) as billable_micro_usd
        ')->first();

        $providerMicro = (int) ($totalsRow?->getAttribute('provider_cost_micro_usd') ?? 0);
        $billableMicro = (int) ($totalsRow?->getAttribute('billable_micro_usd') ?? 0);

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'event_count' => (int) ($totalsRow?->getAttribute('event_count') ?? 0),
            'total_tokens' => (int) ($totalsRow?->getAttribute('total_tokens') ?? 0),
            'provider_cost_usd' => $this->ledger->formatUsd($providerMicro),
            'billable_usd' => $this->ledger->formatUsd($billableMicro),
            'margin_usd' => $this->ledger->formatUsd(max(0, $billableMicro - $providerMicro)),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function eventsPaginated(
        int $perPage = 20,
        ?string $from = null,
        ?string $to = null,
        ?string $modelSlug = null,
        ?string $modelCardId = null,
        ?string $userId = null,
        ?string $stage = null,
        ?string $inferenceProviderId = null,
    ): LengthAwarePaginator {
        return $this->usageEventsQuery($from, $to, $modelSlug, $modelCardId, $userId, $stage, $inferenceProviderId)
            ->with($this->ledger->usageEventRelations())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return Builder<Model>
     */
    private function usageEventsQuery(
        ?string $from,
        ?string $to,
        ?string $modelSlug = null,
        ?string $modelCardId = null,
        ?string $userId = null,
        ?string $stage = null,
        ?string $inferenceProviderId = null,
    ): Builder {
        $model = $this->ledger->usageEventModel();

        /** @var Builder<Model> $query */
        $query = $model::query();

        $query->whereNull('voided_at');
        $this->applyDateFilters($query, $from, $to);

        if ($inferenceProviderId !== null && $inferenceProviderId !== '') {
            $query->where('inference_provider_id', $inferenceProviderId);
        }

        if ($userId !== null && $userId !== '') {
            $query->where('user_id', $userId);
        }

        if ($stage !== null && $stage !== '') {
            $query->where('stage', $stage);
        }

        if ($modelCardId !== null && $modelCardId !== '') {
            $query->where(function (Builder $inner) use ($modelCardId, $modelSlug): void {
                $inner->whereHas(
                    'aiModelRateSnapshot',
                    fn (Builder $snap) => $snap->where('model_card_id', $modelCardId),
                );

                if ($modelSlug !== null && $modelSlug !== '') {
                    $inner->orWhere('resolved_model', $modelSlug)
                        ->orWhere('alias_model', $modelSlug);
                }
            });
        } elseif ($modelSlug !== null && $modelSlug !== '') {
            $query->where(function (Builder $inner) use ($modelSlug): void {
                $inner->where('resolved_model', $modelSlug)
                    ->orWhere('alias_model', $modelSlug);
            });
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyDateFilters(Builder $query, ?string $from, ?string $to): void
    {
        if ($from !== null && $from !== '') {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to !== null && $to !== '') {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }
    }
}
