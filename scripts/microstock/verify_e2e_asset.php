<?php

/**
 * E2E verify: dispatchSync one asset through the real job + log stage summary.
 *
 * Usage: php scripts/microstock/verify_e2e_asset.php [--folder=019fa42d-...]
 *
 * Lives here (not in the gitignored `storage/app/`) so a clean checkout can run
 * it; the report still lands in `storage/app/probe_results/` like the probes.
 */

use App\Domain\Microstock\Jobs\AnalyzeAssetMetadata;
use App\Domain\Microstock\Models\AssetMetadata;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$folderId = '019fa42d-3b38-716a-a302-d7249685de5a';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--folder=')) {
        $folderId = substr($arg, 9);
    }
}

$metadata = AssetMetadata::query()
    ->whereHas('file', fn ($q) => $q->where('folder_id', $folderId))
    ->with('file')
    ->orderBy('id')
    ->first();

if ($metadata?->file === null) {
    fwrite(STDERR, "No metadata for folder {$folderId}\n");
    exit(1);
}

$hint = 'Children doing sports';
$logPath = storage_path('logs/laravel.log');
$logOffset = is_file($logPath) ? filesize($logPath) : 0;

echo "E2E: {$metadata->file->name} (metadata {$metadata->id})\n";
echo 'Vision endpoint: '.config('microstock.ai.vision.endpoint')."\n";
echo 'Vision model: '.config('microstock.ai.vision.model')."\n";

$started = microtime(true);
AnalyzeAssetMetadata::dispatchSync($metadata->id, $hint);
$durationMs = (int) round((microtime(true) - $started) * 1000);

$metadata->refresh();
$stages = parseStageLogs($logPath, $logOffset);

$report = [
    'status' => $metadata->status->value ?? (string) $metadata->status,
    'duration_ms' => $durationMs,
    'title' => $metadata->title,
    'keyword_count' => is_array($metadata->keywords) ? count($metadata->keywords) : 0,
    'has_ai_raw_thinking' => is_array($metadata->ai_raw) && array_key_exists('thinking', $metadata->ai_raw),
    'stages' => $stages,
];

$outDir = storage_path('app/probe_results');
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
$path = $outDir.'/e2e_openwebui_'.date('Ymd_His').'.json';
file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode($report, JSON_PRETTY_PRINT)."\n";
echo "Report: {$path}\n";

/**
 * @return list<array<string, mixed>>
 */
function parseStageLogs(string $logPath, int $offset): array
{
    if (! is_file($logPath)) {
        return [];
    }

    $chunk = file_get_contents($logPath, false, null, $offset);
    if ($chunk === false || $chunk === '') {
        return [];
    }

    $stages = [];
    foreach (explode("\n", $chunk) as $line) {
        if (! str_contains($line, 'microstock.ai.stage')) {
            continue;
        }
        if (preg_match('/"stage":"([^"]+)".*"event":"([^"]+)".*"duration_ms":(\d+)/', $line, $m)) {
            $stages[] = [
                'stage' => $m[1],
                'event' => $m[2],
                'duration_ms' => (int) $m[3],
            ];

            continue;
        }
        if (preg_match('/"stage":"([^"]+)".*"event":"([^"]+)"/', $line, $m)) {
            $stages[] = [
                'stage' => $m[1],
                'event' => $m[2],
                'duration_ms' => null,
            ];
        }
    }

    return $stages;
}
