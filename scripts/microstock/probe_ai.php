<?php

/**
 * Open WebUI microstock AI diagnostic (T0, T0t, T3, T6).
 *
 * Usage: php scripts/microstock/probe_ai.php [--timeout=300] [--only=T0,T0t,T3,T6] [--label=sonnet5-gpt56] [--folder=uuid] [--file=uuid] [--delivery=base64|url] [--variant=ai_512|ai_1024] [--matrix]
 */

use App\Domain\Ai\Services\AiConnectionResolver;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\HttpAiMetadataService;
use App\Domain\Microstock\Services\VisionImageResolver;
use App\Domain\Microstock\Support\Prompts;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$timeout = 300;
$only = null;
$label = 'sonnet5-gpt56';
$folderId = '019fa42d-3b38-716a-a302-d7249685de5a';
$fileId = null;
$delivery = null;
$variantKind = null;
$matrix = false;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--timeout=')) {
        $timeout = max(30, (int) substr($arg, 10));
    }
    if (str_starts_with($arg, '--only=')) {
        $only = array_map('trim', explode(',', substr($arg, 7)));
    }
    if (str_starts_with($arg, '--label=')) {
        $label = preg_replace('/[^a-zA-Z0-9._-]/', '-', substr($arg, 8)) ?: 'sonnet5-gpt56';
    }
    if (str_starts_with($arg, '--folder=')) {
        $folderId = substr($arg, 9);
    }
    if (str_starts_with($arg, '--file=')) {
        $fileId = substr($arg, 7);
    }
    if (str_starts_with($arg, '--delivery=')) {
        $delivery = substr($arg, 11);
    }
    if (str_starts_with($arg, '--variant=')) {
        $variantKind = substr($arg, 10);
    }
    if ($arg === '--matrix') {
        $matrix = true;
    }
}

$resolver = app(AiConnectionResolver::class);
$visionCfg = (array) setting_config('microstock.ai.vision');
$thinkingCfg = (array) setting_config('microstock.ai.thinking');
$visionConn = $resolver->resolve(trim((string) ($visionCfg['connection'] ?? '')) ?: null);
$thinkingConn = $resolver->resolve(trim((string) ($thinkingCfg['connection'] ?? '')) ?: null);
$endpoint = (string) ($visionConn?->endpoint ?? '');
$model = (string) ($visionCfg['model'] ?? '');
$apiKey = (string) ($visionConn?->apiKey ?? '');
$thinkingEndpoint = (string) ($thinkingConn?->endpoint ?? '');
$thinkingModel = (string) ($thinkingCfg['model'] ?? '');
$thinkingApiKey = (string) ($thinkingConn?->apiKey ?? '');

warnIfNotOpenWebUi($endpoint, $model);

$probeFile = null;

if ($fileId !== null && $fileId !== '') {
    $probeFile = HostedFile::query()->find($fileId);
    if ($probeFile === null) {
        fwrite(STDERR, "HostedFile not found: {$fileId}\n");
        exit(1);
    }
    $folderId = (string) ($probeFile->folder_id ?? $folderId);
}

if ($probeFile === null) {
    $metadata = AssetMetadata::query()
        ->whereHas('file', fn ($q) => $q->where('folder_id', $folderId))
        ->with('file')
        ->first();

    $probeFile = $metadata?->file;
}

if ($probeFile === null) {
    $probeFile = HostedFile::query()
        ->where('folder_id', $folderId)
        ->orderBy('created_at')
        ->first();
    if ($probeFile !== null) {
        fwrite(STDERR, "WARNING: no AssetMetadata in folder; using HostedFile {$probeFile->name}\n");
    }
}

if ($probeFile === null) {
    $metadata = AssetMetadata::query()->with('file')->first();
    if ($metadata?->file !== null) {
        $probeFile = $metadata->file;
        $folderId = (string) $probeFile->folder_id;
        fwrite(STDERR, "WARNING: using first available asset in folder {$folderId}\n");
    }
}

if ($probeFile === null) {
    fwrite(STDERR, "No asset found for folder {$folderId}\n");
    exit(1);
}

$outDir = storage_path('app/probe_results');
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$imageResolver = app(VisionImageResolver::class);
$resolveImage = function (?string $deliveryOverride = null, ?string $variantOverride = null) use ($imageResolver, $probeFile): string {
    return $imageResolver->resolve(
        $probeFile,
        $deliveryOverride,
        $variantOverride,
    );
};

$activeDelivery = $delivery ?? (string) setting_config('microstock.ai.vision_image.delivery', 'base64');
$activeVariant = $variantKind ?? (string) setting_config('microstock.ai.vision_image.variant_kind', 'ai_1024');
$visionImage = $resolveImage($delivery, $variantKind);
$hint = 'Children doing sports';

$report = [
    'captured_at' => now()->toIso8601String(),
    'label' => $label,
    'folder_id' => $folderId,
    'file_id' => (string) $probeFile->id,
    'config_delivery' => (string) setting_config('microstock.ai.vision_image.delivery', 'base64'),
    'timeout_seconds' => $timeout,
    'delivery' => $activeDelivery,
    'variant_kind' => $activeVariant,
    'vision_endpoint' => $endpoint,
    'vision_model' => $model,
    'thinking_endpoint' => $thinkingEndpoint,
    'thinking_model' => $thinkingModel,
    'thinking_visual_attempts' => (int) setting_config('microstock.ai.thinking_visual_attempts', 2),
    'file' => $probeFile->name,
    'image_payload_bytes' => strlen($visionImage),
    'tests' => [],
];

$tests = [
    'T0' => fn () => runChat($endpoint, $apiKey, $model, $timeout, [
        ['role' => 'user', 'content' => 'Reply with JSON only: {"ok": true}'],
    ], true),
    'T0t' => fn () => runChat($thinkingEndpoint, $thinkingApiKey, $thinkingModel, $timeout, [
        ['role' => 'user', 'content' => 'Reply with JSON only: {"ok": true}'],
    ], true),
    'T3' => function () use ($endpoint, $apiKey, $model, $timeout, $visionImage, $hint, $activeDelivery, $activeVariant) {
        $result = runVisionImage($endpoint, $apiKey, $model, $timeout, $visionImage, $hint, true);
        $result['delivery'] = $activeDelivery;
        $result['variant_kind'] = $activeVariant;
        $result['image_payload_bytes'] = strlen($visionImage);

        return $result;
    },
    'T6' => function () use ($visionImage, $hint, $activeDelivery, $activeVariant) {
        $started = microtime(true);
        try {
            $result = (new HttpAiMetadataService)->generate($visionImage, $hint);

            $stageMetrics = $result['stage_metrics'] ?? [];
            $stageSummary = summarizeStageMetrics($stageMetrics);

            return [
                'test_id' => 'T6',
                'status' => 'ok',
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'delivery' => $activeDelivery,
                'variant_kind' => $activeVariant,
                'image_payload_bytes' => strlen($visionImage),
                'title' => $result['title'],
                'description' => $result['description'] ?? '',
                'category' => $result['category'] ?? '',
                'keywords' => $result['keywords'],
                'keyword_count' => count($result['keywords']),
                'keywords_preview' => array_slice($result['keywords'], 0, 10),
                'has_thinking_raw' => $result['raw']['thinking'] !== null,
                'stage_metrics' => $stageMetrics,
                'vision_duration_ms' => $stageSummary['vision_duration_ms'] ?? null,
                'thinking_duration_ms' => $stageSummary['thinking_duration_ms'] ?? null,
                'vision_resolved_model' => $stageSummary['vision_resolved_model'] ?? null,
                'thinking_resolved_model' => $stageSummary['thinking_resolved_model'] ?? null,
                'thinking_multimodal' => $stageSummary['thinking_multimodal'] ?? null,
                'vision_tokens_per_second' => $stageSummary['vision_tokens_per_second'] ?? null,
                'thinking_tokens_per_second' => $stageSummary['thinking_tokens_per_second'] ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'test_id' => 'T6',
                'status' => 'error',
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'delivery' => $activeDelivery,
                'variant_kind' => $activeVariant,
                'image_payload_bytes' => strlen($visionImage),
                'error' => $e->getMessage(),
            ];
        }
    },
];

if ($matrix) {
    foreach (['base64', 'url'] as $matrixDelivery) {
        foreach (['ai_512', 'ai_1024'] as $matrixVariant) {
            $matrixImage = $resolveImage($matrixDelivery, $matrixVariant);
            $testId = 'T3_'.$matrixDelivery.'_'.$matrixVariant;
            $tests[$testId] = function () use ($endpoint, $apiKey, $model, $timeout, $matrixImage, $hint, $matrixDelivery, $matrixVariant) {
                $result = runVisionImage($endpoint, $apiKey, $model, $timeout, $matrixImage, $hint, true);
                $result['test_id'] = 'T3';
                $result['delivery'] = $matrixDelivery;
                $result['variant_kind'] = $matrixVariant;
                $result['image_payload_bytes'] = strlen($matrixImage);

                return $result;
            };
        }
    }
}

foreach ($tests as $id => $runner) {
    if ($only !== null && ! in_array($id, $only, true)) {
        continue;
    }

    echo "\n=== Running {$id} (timeout {$timeout}s) ===\n";
    $result = $runner();
    $report['tests'][$id] = $result;
    echo json_encode($result, JSON_PRETTY_PRINT)."\n";
    flush();
}

$report['summary'] = buildProbeSummary($report['tests']);

$safeLabel = preg_replace('/[^a-zA-Z0-9._-]/', '-', $label) ?: 'sonnet5-gpt56';
$path = $outDir.'/'.$safeLabel.'_'.date('Ymd_His').'.json';
file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "\n=== Summary ===\n";
echo json_encode($report['summary'], JSON_PRETTY_PRINT)."\n";
echo "\nFull report: {$path}\n";

/**
 * @param  array<string, array<string, mixed>>  $tests
 * @return array<string, array<string, mixed>>
 */
function buildProbeSummary(array $tests): array
{
    $summary = [];
    foreach ($tests as $id => $result) {
        $entry = [
            'status' => $result['status'] ?? 'unknown',
            'duration_ms' => $result['duration_ms'] ?? null,
            'resolved_model' => $result['resolved_model'] ?? null,
            'tokens_per_second' => $result['tokens_per_second'] ?? null,
            'delivery' => $result['delivery'] ?? null,
            'variant_kind' => $result['variant_kind'] ?? null,
            'image_payload_bytes' => $result['image_payload_bytes'] ?? null,
        ];

        if ($id === 'T6' || ($result['test_id'] ?? null) === 'T6') {
            $entry['title'] = $result['title'] ?? null;
            $entry['keyword_count'] = $result['keyword_count'] ?? null;
            $entry['keywords_preview'] = $result['keywords_preview'] ?? null;
            $entry['vision_duration_ms'] = $result['vision_duration_ms'] ?? null;
            $entry['thinking_duration_ms'] = $result['thinking_duration_ms'] ?? null;
            $entry['vision_resolved_model'] = $result['vision_resolved_model'] ?? null;
            $entry['thinking_resolved_model'] = $result['thinking_resolved_model'] ?? null;
            $entry['thinking_multimodal'] = $result['thinking_multimodal'] ?? null;
            $entry['vision_tokens_per_second'] = $result['vision_tokens_per_second'] ?? null;
            $entry['thinking_tokens_per_second'] = $result['thinking_tokens_per_second'] ?? null;
        }

        $summary[$id] = $entry;
    }

    return $summary;
}

/**
 * @param  list<array<string, mixed>>  $stageMetrics
 * @return array<string, mixed>
 */
function summarizeStageMetrics(array $stageMetrics): array
{
    $summary = [];
    $successfulThinking = null;

    foreach ($stageMetrics as $metric) {
        $stage = (string) ($metric['stage'] ?? '');
        if ($stage === 'vision' && ($metric['status'] ?? '') === 'ok') {
            $summary['vision_duration_ms'] = $metric['duration_ms'] ?? null;
            $summary['vision_resolved_model'] = $metric['resolved_model'] ?? null;
            $summary['vision_tokens_per_second'] = $metric['tokens_per_second'] ?? null;
        }
        if ($stage === 'thinking' && ($metric['status'] ?? '') === 'ok') {
            $successfulThinking = $metric;
        }
    }

    if (is_array($successfulThinking)) {
        $summary['thinking_duration_ms'] = $successfulThinking['duration_ms'] ?? null;
        $summary['thinking_resolved_model'] = $successfulThinking['resolved_model'] ?? null;
        $summary['thinking_multimodal'] = $successfulThinking['multimodal'] ?? null;
        $summary['thinking_tokens_per_second'] = $successfulThinking['tokens_per_second'] ?? null;
    }

    return $summary;
}

/**
 * @param  array<string, mixed>|null  $usage
 */
function tokensPerSecond(?array $usage, int $durationMs): ?float
{
    if ($durationMs <= 0 || ! is_array($usage)) {
        return null;
    }

    $total = (int) ($usage['total_tokens'] ?? 0);
    if ($total <= 0) {
        $total = (int) ($usage['prompt_tokens'] ?? 0) + (int) ($usage['completion_tokens'] ?? 0);
    }
    if ($total <= 0) {
        return null;
    }

    return round($total / ($durationMs / 1000), 2);
}

function warnIfNotOpenWebUi(string $endpoint, string $model): void
{
    $warnings = [];
    if (! str_contains($endpoint, '/api/chat/completions')) {
        $warnings[] = "endpoint does not look like Open WebUI (/api/chat/completions): {$endpoint}";
    }
    if (! str_starts_with($model, 'internal.')) {
        $warnings[] = "vision model is not an internal.* alias: {$model}";
    }
    foreach ($warnings as $warning) {
        fwrite(STDERR, "WARNING: {$warning}\n");
    }
}

/**
 * @param  list<array<string, mixed>>  $messages
 * @return array<string, mixed>
 */
function runChat(string $endpoint, string $apiKey, string $model, int $timeout, array $messages, bool $jsonObject): array
{
    $payload = [
        'model' => $model,
        'stream' => false,
        'temperature' => 0.2,
        'max_tokens' => 500,
        'messages' => $messages,
    ];
    if ($jsonObject) {
        $payload['response_format'] = ['type' => 'json_object'];
    }

    return executeProbe('chat', $endpoint, $apiKey, $timeout, $payload);
}

function runVisionImage(string $endpoint, string $apiKey, string $model, int $timeout, string $imageUrl, ?string $hint, bool $jsonObject): array
{
    $messages = [
        ['role' => 'system', 'content' => Prompts::visionSystem()],
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => Prompts::visionUser($hint)],
                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
            ],
        ],
    ];

    $payload = [
        'model' => $model,
        'stream' => false,
        'temperature' => 0.2,
        'max_tokens' => 2000,
        'messages' => $messages,
    ];
    if ($jsonObject) {
        $payload['response_format'] = ['type' => 'json_object'];
    }

    $result = executeProbe('vision_image', $endpoint, $apiKey, $timeout, $payload);
    $result['test_id'] = 'T3';
    $result['json_object'] = $jsonObject;

    return $result;
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function executeProbe(string $kind, string $endpoint, string $apiKey, int $timeout, array $payload): array
{
    $started = microtime(true);
    $result = [
        'kind' => $kind,
        'status' => 'unknown',
        'duration_ms' => 0,
        'http_status' => null,
        'body_bytes' => 0,
        'body_prefix' => null,
        'finish_reason' => null,
        'resolved_model' => null,
        'tokens_per_second' => null,
        'usage' => null,
        'error' => null,
    ];

    try {
        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->connectTimeout(10)
            ->post($endpoint, $payload);

        $result['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
        $body = $response->body();
        $result['http_status'] = $response->status();
        $result['body_bytes'] = strlen($body);
        $result['body_prefix'] = mb_substr(trim($body), 0, 120);

        if (! $response->successful()) {
            $result['status'] = 'http_error';

            return $result;
        }

        $json = $response->json();
        if (! is_array($json)) {
            $json = json_decode(trim(preg_replace('/\s*data:\s*\[DONE\]\s*$/s', '', trim($body))), true);
        }

        if (is_array($json)) {
            $result['resolved_model'] = is_string($json['model'] ?? null) ? $json['model'] : null;
            $result['finish_reason'] = $json['choices'][0]['finish_reason'] ?? null;
            $result['usage'] = $json['usage'] ?? null;
            $result['tokens_per_second'] = tokensPerSecond($result['usage'], $result['duration_ms']);
            $content = $json['choices'][0]['message']['content'] ?? null;
            if (is_string($content) && $content !== '') {
                $result['status'] = 'ok';
                $result['content_preview'] = mb_substr($content, 0, 200);
            } else {
                $result['status'] = 'empty_content';
            }
        } else {
            $result['status'] = str_starts_with(trim($body), 'data:') ? 'sse_unparsed' : 'unparseable';
        }
    } catch (Throwable $e) {
        $result['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
        $result['status'] = str_contains($e->getMessage(), 'timed out') ? 'timeout' : 'exception';
        $result['error'] = $e->getMessage();
    }

    return $result;
}
