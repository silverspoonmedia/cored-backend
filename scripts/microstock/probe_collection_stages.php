<?php

/**
 * E2E decoupled collection probe: runs CollectionService::analyze() and reports
 * per-asset ai_vision / ai_thinking / ai_result stage columns.
 *
 * Usage: php scripts/microstock/probe_collection_stages.php {folder_id} [--label=decoupled-probe-e2e] [--wait-timeout=900] [--force] [--dry-run]
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AnalysisStageService;
use App\Domain\Microstock\Services\CollectionService;

config(['queue.default' => 'sync']);

$folderId = null;
$label = 'decoupled-probe-e2e';
$waitTimeout = 900;
$dryRun = false;
$force = false;

foreach ($argv as $i => $arg) {
    if ($i === 0) {
        continue;
    }
    if (str_starts_with($arg, '--label=')) {
        $label = preg_replace('/[^a-zA-Z0-9._-]/', '-', substr($arg, 8)) ?: 'decoupled-probe-e2e';
    } elseif (str_starts_with($arg, '--wait-timeout=')) {
        $waitTimeout = max(30, (int) substr($arg, 15));
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--force') {
        $force = true;
    } elseif (! str_starts_with($arg, '--') && $folderId === null) {
        $folderId = $arg;
    }
}

if ($folderId === null) {
    fwrite(STDERR, "Usage: php scripts/microstock/probe_collection_stages.php {folder_id} [--label=decoupled-probe-e2e] [--wait-timeout=900] [--force] [--dry-run]\n");
    exit(1);
}

$folder = Folder::query()->find($folderId);
if ($folder === null) {
    fwrite(STDERR, "Folder not found: {$folderId}\n");
    exit(1);
}

$files = HostedFile::query()
    ->where('folder_id', $folder->id)
    ->orderBy('created_at')
    ->get();

if ($files->isEmpty()) {
    fwrite(STDERR, "No files in folder {$folderId}\n");
    exit(1);
}

$owner = User::query()->find($folder->user_id);
if ($owner === null) {
    fwrite(STDERR, "Folder owner not found (user_id={$folder->user_id})\n");
    exit(1);
}

$status = $folder->collection_status;
$collectionService = app(CollectionService::class);

if ($force) {
    AssetMetadata::query()
        ->whereIn('file_id', $files->pluck('id'))
        ->delete();

    if ($status !== CollectionStatus::Locked && $status !== CollectionStatus::Draft) {
        $folder->markCollectionStatus(CollectionStatus::Failed);
        $folder->refresh();
    }

    $hint = trim((string) ($folder->collection_hint ?? ''));
    if ($hint === '') {
        fwrite(STDERR, "Cannot --force re-lock: collection_hint is empty.\n");
        exit(1);
    }

    if ($folder->collection_status !== CollectionStatus::Locked) {
        $folder = $collectionService->lock($owner, $folderId, $hint);
    }

    $status = $folder->collection_status;
}

$shouldAnalyze = $status === CollectionStatus::Locked;

if (! $shouldAnalyze && $status !== CollectionStatus::Processing) {
    fwrite(STDERR, 'Folder must be locked or already processing (current: '.($status?->value ?? 'null')."). Use --force to reset a stale run.\n");
    exit(1);
}

echo "Mode: ".($shouldAnalyze ? 'analyze + wait' : 'wait (already processing)')."\n";

echo "Folder: {$folder->id}\n";
echo "Workspace: {$folder->workspace_id}\n";
echo "Files: {$files->count()}\n";
echo "Hint: ".($folder->collection_hint ?? '(none)')."\n";
echo "Label: {$label}\n";
echo "Queue: sync\n";

if ($dryRun) {
    echo "Dry run — skipping analyze.\n";
    exit(0);
}

$startedAt = microtime(true);

if ($shouldAnalyze) {
    try {
        app(CollectionService::class)->analyze($owner, $folderId);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Analyze failed: '.$e->getMessage()."\n");
        exit(1);
    }
}

$stages = app(AnalysisStageService::class);
$deadline = time() + $waitTimeout;
$pollStartedAt = time();
$lastProgressAt = 0;
$zombieGraceSeconds = 60;

while (time() < $deadline) {
    $folder->refresh();
    $metadataRows = AssetMetadata::query()
        ->whereIn('file_id', $files->pluck('id'))
        ->get()
        ->keyBy('file_id');

    $metadataCount = $metadataRows->count();
    $elapsed = time() - $pollStartedAt;

    if (
        $folder->collection_status === CollectionStatus::Processing
        && $metadataCount === 0
        && $elapsed >= $zombieGraceSeconds
        && ! $shouldAnalyze
    ) {
        fwrite(STDERR, "Zombie run detected: folder is processing but no metadata rows after {$zombieGraceSeconds}s. Re-run with --force.\n");
        exit(1);
    }

    $allTerminal = true;
    foreach ($files as $file) {
        $row = $metadataRows->get($file->id);
        if ($row === null || ! $stages->isTerminal($row)) {
            $allTerminal = false;
            break;
        }
    }

    if ($allTerminal && $folder->collection_status !== CollectionStatus::Processing) {
        break;
    }

    if ($allTerminal && $folder->collection_status === CollectionStatus::Processing) {
        $stages->completeFolderIfReady($folderId);
        $folder->refresh();
        if ($folder->collection_status !== CollectionStatus::Processing) {
            break;
        }
    }

    if ($elapsed - $lastProgressAt >= 30) {
        $lastProgressAt = $elapsed;
        $stageCounts = ['pending' => 0, 'running' => 0, 'ok' => 0, 'failed' => 0];
        foreach ($metadataRows as $row) {
            foreach (['ai_vision', 'ai_thinking', 'ai_result'] as $col) {
                $stageStatus = is_array($row->{$col}) ? ($row->{$col}['status'] ?? 'pending') : 'pending';
                $stageCounts[$stageStatus] = ($stageCounts[$stageStatus] ?? 0) + 1;
            }
        }
        echo sprintf(
            "[%ds] folder=%s metadata=%d/%d stages=%s\n",
            $elapsed,
            $folder->collection_status?->value ?? 'null',
            $metadataCount,
            $files->count(),
            json_encode($stageCounts)
        );
    }

    usleep(500_000);
}

if (time() >= $deadline) {
    fwrite(STDERR, "Timed out after {$waitTimeout}s waiting for terminal stage state.\n");
}

$folder->refresh();
$elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

$fileResults = [];
foreach ($files as $file) {
    $m = AssetMetadata::query()->where('file_id', $file->id)->first();
    $fileResults[] = summarizeFile($file, $m, $stages);
}

$aggregate = [
    'captured_at' => now()->toIso8601String(),
    'label' => $label,
    'folder_id' => $folderId,
    'workspace_id' => (string) $folder->workspace_id,
    'collection_hint' => $folder->collection_hint,
    'collection_status' => $folder->collection_status?->value,
    'file_count' => $files->count(),
    'wait_timeout_seconds' => $waitTimeout,
    'elapsed_ms' => $elapsedMs,
    'vision_model' => config('microstock.ai.vision.model'),
    'thinking_model' => config('microstock.ai.thinking.model'),
    'files' => $fileResults,
];

$outDir = storage_path('app/probe_results');
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$timestamp = date('Ymd_His');
$safeFolder = preg_replace('/[^a-zA-Z0-9._-]/', '-', $folderId);
$path = $outDir.'/collection_stages_'.$safeFolder.'_'.$timestamp.'.json';
file_put_contents($path, json_encode($aggregate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "\n=== Collection stage probe complete ===\n";
echo json_encode($aggregate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
echo "\nReport: {$path}\n";

$stuck = array_filter($fileResults, fn (array $r): bool => ! in_array($r['status'], [
    MetadataStatus::Generated->value,
    MetadataStatus::Finalized->value,
    MetadataStatus::Failed->value,
], true));

exit($stuck === [] ? 0 : 1);

/**
 * @return array<string, mixed>
 */
function summarizeFile(HostedFile $file, ?AssetMetadata $m, AnalysisStageService $stages): array
{
    if ($m === null) {
        return [
            'file_id' => (string) $file->id,
            'file_name' => $file->name,
            'status' => null,
            'error' => 'no metadata row',
            'stages' => ['vision' => 'pending', 'thinking' => 'pending', 'result' => 'pending'],
        ];
    }

    $vision = is_array($m->ai_vision) ? $m->ai_vision : [];
    $thinking = is_array($m->ai_thinking) ? $m->ai_thinking : [];
    $result = is_array($m->ai_result) ? $m->ai_result : [];

    $visionMetric = firstOkStageMetric($vision['stage_metrics'] ?? [], 'vision');
    $thinkingMetric = firstOkStageMetric($thinking['stage_metrics'] ?? [], 'thinking');

    $visionMs = $visionMetric['duration_ms'] ?? null;
    $thinkingMs = $thinkingMetric['duration_ms'] ?? null;

    return [
        'file_id' => (string) $file->id,
        'file_name' => $file->name,
        'status' => $m->status?->value,
        'title' => $m->title,
        'keywords' => $m->keywords ?? [],
        'keyword_count' => is_array($m->keywords) ? count($m->keywords) : 0,
        'error' => $m->error,
        'stages' => $stages->stageSummary($m),
        'ai_vision' => [
            'status' => $vision['status'] ?? AnalysisStageStatus::Pending->value,
            'started_at' => $vision['started_at'] ?? null,
            'completed_at' => $vision['completed_at'] ?? null,
            'error' => $vision['error'] ?? null,
            'output_title' => is_array($vision['output'] ?? null) ? ($vision['output']['title'] ?? null) : null,
            'stage_metrics' => $vision['stage_metrics'] ?? [],
        ],
        'ai_thinking' => [
            'status' => $thinking['status'] ?? AnalysisStageStatus::Pending->value,
            'started_at' => $thinking['started_at'] ?? null,
            'completed_at' => $thinking['completed_at'] ?? null,
            'error' => $thinking['error'] ?? null,
            'stage_metrics' => $thinking['stage_metrics'] ?? [],
        ],
        'ai_result' => [
            'status' => $result['status'] ?? AnalysisStageStatus::Pending->value,
            'source' => $result['source'] ?? null,
            'completed_at' => $result['completed_at'] ?? null,
            'error' => $result['error'] ?? null,
        ],
        'vision_duration_ms' => $visionMs,
        'thinking_duration_ms' => $thinkingMs,
        'vision_tokens_per_second' => $visionMetric['tokens_per_second'] ?? null,
        'thinking_tokens_per_second' => $thinkingMetric['tokens_per_second'] ?? null,
        'vision_resolved_model' => $visionMetric['resolved_model'] ?? null,
        'thinking_resolved_model' => $thinkingMetric['resolved_model'] ?? null,
        'e2e_wall_ms' => ($visionMs !== null && $thinkingMs !== null) ? $visionMs + $thinkingMs : null,
    ];
}

/**
 * @param  list<array<string, mixed>>  $metrics
 * @return array<string, mixed>
 */
function firstOkStageMetric(array $metrics, string $stage): array
{
    foreach ($metrics as $metric) {
        if (($metric['stage'] ?? '') === $stage && ($metric['status'] ?? '') === 'ok') {
            return $metric;
        }
    }

    return [];
}
