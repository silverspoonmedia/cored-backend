<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Microstock\Enums\AiCreditTransactionType;
use App\Domain\Microstock\Models\AiBalance;
use App\Domain\Microstock\Models\AiCreditTransaction;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Models\DailyFreeUsage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminBillingAnalyticsService
{
    public function __construct(
        private readonly AiDailyFreeService $dailyFree,
        private readonly BillingLedgerService $ledger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function platformOverview(?string $from = null, ?string $to = null): array
    {
        $eventsQuery = $this->usageEventsQuery($from, $to);

        $totalsRow = (clone $eventsQuery)->selectRaw('
            COUNT(*) as event_count,
            COUNT(DISTINCT user_id) as unique_users,
            COALESCE(SUM(total_tokens), 0) as total_tokens,
            COALESCE(SUM(provider_cost_micro_usd), 0) as provider_cost_micro_usd,
            COALESCE(SUM(billable_micro_usd), 0) as billable_micro_usd
        ')->first();

        $providerMicro = (int) ($totalsRow->provider_cost_micro_usd ?? 0);
        $billableMicro = (int) ($totalsRow->billable_micro_usd ?? 0);

        $bySource = (clone $eventsQuery)
            ->selectRaw('
                billing_source,
                COUNT(*) as event_count,
                COALESCE(SUM(provider_cost_micro_usd), 0) as provider_cost_micro_usd,
                COALESCE(SUM(billable_micro_usd), 0) as billable_micro_usd
            ')
            ->groupBy('billing_source')
            ->get()
            ->mapWithKeys(function ($row): array {
                $source = (string) ($row->billing_source ?? 'unknown');

                return [$source => [
                    'event_count' => (int) $row->event_count,
                    'provider_cost_usd' => $this->ledger->formatUsd((int) $row->provider_cost_micro_usd),
                    'billable_usd' => $this->ledger->formatUsd((int) $row->billable_micro_usd),
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
            ->map(fn ($row): array => [
                'stage' => (string) ($row->stage ?? ''),
                'event_count' => (int) $row->event_count,
                'provider_cost_usd' => $this->ledger->formatUsd((int) $row->provider_cost_micro_usd),
                'billable_usd' => $this->ledger->formatUsd((int) $row->billable_micro_usd),
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
            ->map(fn ($row): array => [
                'resolved_model' => (string) $row->model_name,
                'event_count' => (int) $row->event_count,
                'total_tokens' => (int) $row->total_tokens,
                'provider_cost_usd' => $this->ledger->formatUsd((int) $row->provider_cost_micro_usd),
                'billable_usd' => $this->ledger->formatUsd((int) $row->billable_micro_usd),
            ])
            ->values()
            ->all();

        $txQuery = AiCreditTransaction::query();
        $this->applyDateFilters($txQuery, $from, $to);

        $purchaseMicro = (int) (clone $txQuery)
            ->where('type', AiCreditTransactionType::Purchase)
            ->sum('amount_micro_usd');
        $debitMicro = (int) (clone $txQuery)
            ->where('type', AiCreditTransactionType::AnalysisDebit)
            ->sum(DB::raw('ABS(amount_micro_usd)'));

        $today = $this->dailyFree->today()->toDateString();
        $dailyFreeRows = DailyFreeUsage::query()->where('usage_date', $today);

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'totals' => [
                'event_count' => (int) ($totalsRow->event_count ?? 0),
                'unique_users' => (int) ($totalsRow->unique_users ?? 0),
                'total_tokens' => (int) ($totalsRow->total_tokens ?? 0),
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
            'credits' => [
                'outstanding_balance_micro_usd' => (int) AiBalance::query()->sum('balance_micro_usd'),
                'outstanding_balance_usd' => $this->ledger->formatUsd((int) AiBalance::query()->sum('balance_micro_usd')),
                'purchase_volume_micro_usd' => $purchaseMicro,
                'purchase_volume_usd' => $this->ledger->formatUsd($purchaseMicro),
                'debit_volume_micro_usd' => $debitMicro,
                'debit_volume_usd' => $this->ledger->formatUsd($debitMicro),
            ],
            'daily_free' => [
                'enabled' => $this->dailyFree->isEnabled(),
                'users_active_today' => (int) (clone $dailyFreeRows)->where('analyses_used', '>', 0)->count(),
                'slots_consumed_today' => (int) (clone $dailyFreeRows)->sum('analyses_used'),
                'subsidized_provider_cost_usd' => $this->ledger->formatUsd(
                    (int) (clone $eventsQuery)->where('billing_source', 'daily_free')->sum('provider_cost_micro_usd'),
                ),
            ],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, AiUsageEvent>
     */
    public function usageEventsPaginated(
        int $perPage = 20,
        ?string $from = null,
        ?string $to = null,
        ?string $userId = null,
    ): LengthAwarePaginator {
        $query = $this->usageEventsQuery($from, $to)
            ->with([
                'file:id,name',
                'folder:id,name',
                'user:id,name,email',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($userId !== null && $userId !== '') {
            $query->where('user_id', $userId);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return Builder<AiUsageEvent>
     */
    private function usageEventsQuery(?string $from, ?string $to): Builder
    {
        $query = AiUsageEvent::query()->whereNull('voided_at');
        $this->applyDateFilters($query, $from, $to);

        return $query;
    }

    /**
     * @param  Builder<AiUsageEvent>|Builder<AiCreditTransaction>  $query
     */
    private function applyDateFilters(mixed $query, ?string $from, ?string $to): void
    {
        if ($from !== null && $from !== '') {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to !== null && $to !== '') {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }
    }
}
