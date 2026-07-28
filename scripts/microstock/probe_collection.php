<?php

/**
 * Run probe_ai.php for every file in a collection folder.
 *
 * Usage: php scripts/microstock/probe_collection.php {folder_id} [--timeout=300] [--only=T3,T6] [--label=sonnet5-gpt56]
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\FileHosting\Models\HostedFile;

$folderId = null;
$timeout = 300;
$only = 'T3,T6';
$labelPrefix = 'sonnet5-gpt56';

foreach ($argv as $i => $arg) {
    if ($i === 0) {
        continue;
    }
    if (str_starts_with($arg, '--timeout=')) {
        $timeout = max(30, (int) substr($arg, 10));
    } elseif (str_starts_with($arg, '--only=')) {
        $only = substr($arg, 7);
    } elseif (str_starts_with($arg, '--label=')) {
        $labelPrefix = preg_replace('/[^a-zA-Z0-9._-]/', '-', substr($arg, 8)) ?: 'sonnet5-gpt56';
    } elseif (! str_starts_with($arg, '--') && $folderId === null) {
        $folderId = $arg;
    }
}

if ($folderId === null) {
    fwrite(STDERR, "Usage: php scripts/microstock/probe_collection.php {folder_id} [--timeout=300] [--only=T3,T6] [--label=sonnet5-gpt56]\n");
    exit(1);
}

$files = HostedFile::query()
    ->where('folder_id', $folderId)
    ->orderBy('created_at')
    ->get();

if ($files->isEmpty()) {
    fwrite(STDERR, "No files in folder {$folderId}\n");
    exit(1);
}

$outDir = storage_path('app/probe_results');
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$aggregate = [
    'captured_at' => now()->toIso8601String(),
    'folder_id' => $folderId,
    'label' => $labelPrefix,
    'vision_model' => config('microstock.ai.vision.model'),
    'thinking_model' => config('microstock.ai.thinking.model'),
    'thinking_visual_attempts' => (int) config('microstock.ai.thinking_visual_attempts', 2),
    'config_delivery' => (string) config('microstock.ai.vision_image.delivery', 'base64'),
    'config_variant_kind' => (string) config('microstock.ai.vision_image.variant_kind', 'ai_1024'),
    'timeout_seconds' => $timeout,
    'files' => [],
];

$probeScript = __DIR__.'/probe_ai.php';

foreach ($files as $file) {
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '-', pathinfo($file->name, PATHINFO_FILENAME)) ?: 'file';
    $label = $labelPrefix.'-'.$safeName;

    echo "\n========== {$file->name} ({$file->id}) ==========\n";

    $cmd = sprintf(
        'php %s --timeout=%d --only=%s --folder=%s --file=%s --label=%s',
        escapeshellarg($probeScript),
        $timeout,
        escapeshellarg($only),
        escapeshellarg($folderId),
        escapeshellarg((string) $file->id),
        escapeshellarg($label),
    );

    passthru($cmd, $exitCode);

    $reports = glob($outDir.'/'.$label.'_*.json');
    rsort($reports);
    $latest = $reports[0] ?? null;

    $fileResult = [
        'file_id' => (string) $file->id,
        'file_name' => $file->name,
        'probe_exit_code' => $exitCode,
        'report_path' => $latest,
        'summary' => null,
        'title' => null,
        'keywords' => null,
    ];

    if ($latest !== null && is_readable($latest)) {
        $decoded = json_decode((string) file_get_contents($latest), true);
        if (is_array($decoded)) {
            $fileResult['summary'] = $decoded['summary'] ?? null;
            $fileResult['delivery'] = $decoded['delivery'] ?? null;
            $fileResult['image_payload_bytes'] = $decoded['image_payload_bytes'] ?? null;

            $t6 = $decoded['tests']['T6'] ?? null;
            if (is_array($t6)) {
                $fileResult['title'] = $t6['title'] ?? null;
                $fileResult['keywords'] = $t6['keywords'] ?? null;
                $fileResult['stage_metrics'] = $t6['stage_metrics'] ?? null;
                $fileResult['thinking_multimodal'] = $t6['thinking_multimodal'] ?? null;
                $fileResult['vision_resolved_model'] = $t6['vision_resolved_model'] ?? null;
                $fileResult['thinking_resolved_model'] = $t6['thinking_resolved_model'] ?? null;
            }
        }
    }

    $aggregate['files'][] = $fileResult;
}

$timestamp = date('Ymd_His');
$aggregatePath = $outDir.'/collection_'.$folderId.'_'.$timestamp.'.json';
file_put_contents($aggregatePath, json_encode($aggregate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "\n=== Collection aggregate ===\n";
echo json_encode($aggregate['files'], JSON_PRETTY_PRINT)."\n";
echo "\nAggregate report: {$aggregatePath}\n";
