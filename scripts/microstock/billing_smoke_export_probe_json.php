<?php

/**
 * Build probe JSON artifact from current DB state (when probe script hung on watchdog loop).
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AnalysisStageService;

$folderId = $argv[1] ?? null;
$label = $argv[2] ?? 'manual-export';
$elapsedMs = isset($argv[3]) ? (int) $argv[3] : 0;

if ($folderId === null) {
    fwrite(STDERR, "Usage: php billing_smoke_export_probe_json.php {folder_id} [label] [elapsed_ms]\n");
    exit(1);
}

$folder = Folder::query()->findOrFail($folderId);
$files = HostedFile::query()->where('folder_id', $folderId)->orderBy('created_at')->get();
$stages = app(AnalysisStageService::class);

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
    'collection_billing_mode' => $folder->collection_billing_mode,
    'file_count' => $files->count(),
    'elapsed_ms' => $elapsedMs,
    'vision_model' => setting_config('microstock.ai.vision.model'),
    'thinking_model' => setting_config('microstock.ai.thinking.model'),
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

echo $path."\n";
echo json_encode($aggregate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

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

    return [
        'file_id' => (string) $file->id,
        'file_name' => $file->name,
        'status' => $m->status?->value,
        'billing_source' => $m->billing_source,
        'title' => $m->title,
        'error' => $m->error,
        'stages' => $stages->stageSummary($m),
        'vision_duration_ms' => $visionMetric['duration_ms'] ?? null,
        'thinking_duration_ms' => $thinkingMetric['duration_ms'] ?? null,
        'vision_tokens_per_second' => $visionMetric['tokens_per_second'] ?? null,
        'thinking_tokens_per_second' => $thinkingMetric['tokens_per_second'] ?? null,
        'vision_resolved_model' => $visionMetric['resolved_model'] ?? null,
        'thinking_resolved_model' => $thinkingMetric['resolved_model'] ?? null,
        'ai_billing' => $m->ai_billing,
    ];
}

function firstOkStageMetric(array $metrics, string $stage): array
{
    foreach ($metrics as $metric) {
        if (($metric['stage'] ?? '') === $stage && ($metric['status'] ?? '') === 'ok') {
            return $metric;
        }
    }

    return [];
}
