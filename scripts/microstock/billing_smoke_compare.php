<?php

/**
 * Aggregate billing smoke test results for free vs paid folders.
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Microstock\Services\BillingLedgerService;
use Illuminate\Contracts\Console\Kernel;

$freeFolderId = '019fbc21-eff1-72af-b14c-494ecbe14328';
$paidFolderId = '019fbc22-1585-72f4-86d5-ac33faf984d5';
$ownerId = '019fbbf6-560b-729e-84de-d921fa2729cf';

$ledger = app(BillingLedgerService::class);
$credits = app(AiCreditService::class);

$balanceBefore = json_decode(
    file_get_contents(storage_path('app/probe_results/billing_smoke_balance_before.json')),
    true,
)['prepaid_balance_micro_usd'] ?? null;
$balanceAfter = $credits->balance($ownerId);

function folderReport(string $folderId, string $label): array
{
    $fileIds = HostedFile::query()->where('folder_id', $folderId)->pluck('id');
    $metadataIds = AssetMetadata::query()->whereIn('file_id', $fileIds)->pluck('id');
    $runIds = AssetMetadata::query()->whereIn('file_id', $fileIds)->pluck('analysis_run_id')->filter()->unique()->values();

    $events = AiUsageEvent::query()
        ->whereIn('asset_metadata_id', $metadataIds)
        ->whereNull('voided_at')
        ->get();

    $byStage = $events->groupBy('stage')->map(fn ($g) => [
        'count' => $g->count(),
        'total_tokens' => (int) $g->sum('total_tokens'),
        'provider_cost_micro_usd' => (int) $g->sum('provider_cost_micro_usd'),
        'billable_micro_usd' => (int) $g->sum('billable_micro_usd'),
    ])->all();

    $metaBilling = AssetMetadata::query()
        ->whereIn('file_id', $fileIds)
        ->get()
        ->map(fn ($m) => [
            'file_id' => $m->file_id,
            'status' => $m->status?->value,
            'billing_source' => $m->billing_source,
            'ai_billing' => $m->ai_billing,
        ])
        ->all();

    return [
        'label' => $label,
        'folder_id' => $folderId,
        'analysis_run_ids' => $runIds->all(),
        'metadata_count' => $metadataIds->count(),
        'usage_event_count' => $events->count(),
        'billing_sources' => $events->pluck('billing_source')->unique()->values()->all(),
        'total_tokens' => (int) $events->sum('total_tokens'),
        'provider_cost_micro_usd' => (int) $events->sum('provider_cost_micro_usd'),
        'billable_micro_usd' => (int) $events->sum('billable_micro_usd'),
        'provider_cost_usd' => app(BillingLedgerService::class)->formatUsd((int) $events->sum('provider_cost_micro_usd')),
        'billable_usd' => app(BillingLedgerService::class)->formatUsd((int) $events->sum('billable_micro_usd')),
        'resolved_models' => $events->pluck('resolved_model')->unique()->filter()->values()->all(),
        'alias_models' => $events->pluck('alias_model')->unique()->filter()->values()->all(),
        'inference_provider_ids' => $events->pluck('inference_provider_id')->unique()->filter()->values()->all(),
        'snapshot_ids' => $events->pluck('ai_model_rate_snapshot_id')->unique()->filter()->values()->all(),
        'by_stage' => $byStage,
        'per_metadata_billing' => $metaBilling,
        'events' => $events->map(fn ($e) => [
            'stage' => $e->stage,
            'billing_source' => $e->billing_source,
            'alias_model' => $e->alias_model,
            'resolved_model' => $e->resolved_model,
            'total_tokens' => $e->total_tokens,
            'provider_cost_usd' => app(BillingLedgerService::class)->formatUsd((int) $e->provider_cost_micro_usd),
            'billable_usd' => app(BillingLedgerService::class)->formatUsd((int) $e->billable_micro_usd),
            'billed_at' => $e->billed_at?->toIso8601String(),
            'inference_provider_id' => $e->inference_provider_id,
            'snapshot_id' => $e->ai_model_rate_snapshot_id,
        ])->values()->all(),
    ];
}

$report = [
    'captured_at' => now()->toIso8601String(),
    'owner_id' => $ownerId,
    'prepaid_balance_micro_usd_before' => $balanceBefore,
    'prepaid_balance_micro_usd_after' => $balanceAfter,
    'prepaid_balance_debit_micro_usd' => $balanceBefore !== null ? $balanceBefore - $balanceAfter : null,
    'prepaid_balance_before_usd' => $balanceBefore !== null ? $ledger->formatUsd($balanceBefore) : null,
    'prepaid_balance_after_usd' => $ledger->formatUsd($balanceAfter),
    'prepaid_balance_debit_usd' => $balanceBefore !== null ? $ledger->formatUsd($balanceBefore - $balanceAfter) : null,
    'free' => folderReport($freeFolderId, 'billing-smoke-free'),
    'paid' => folderReport($paidFolderId, 'billing-smoke-paid'),
];

$path = storage_path('app/probe_results/billing_smoke_comparison.json');
file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
echo "\nReport: {$path}\n";
