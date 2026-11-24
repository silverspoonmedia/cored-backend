<?php

/**
 * Read-only: list the model aliases the stored AI connection advertises.
 *
 * Tries the usual catalogue paths relative to the connection endpoint
 * (Open WebUI: /api/models) with the stored key. The key is never printed.
 *
 * Usage: php scripts/ai/list_models.php [slug]
 */

use App\Domain\Ai\Models\AiConnection;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$slug = $argv[1] ?? null;

if ($slug === null || $slug === '') {
    $slugs = AiConnection::query()->orderBy('slug')->pluck('slug')->all();

    echo 'Usage: php scripts/ai/list_models.php <slug>'.PHP_EOL;
    echo $slugs === []
        ? 'No ai_connections rows stored yet.'.PHP_EOL
        : 'Stored connections: '.implode(', ', $slugs).PHP_EOL;

    exit(1);
}

$connection = AiConnection::query()->where('slug', $slug)->first();

if ($connection === null) {
    echo "No ai_connections row for slug '{$slug}'.".PHP_EOL;

    exit(1);
}

$payload = $connection->secret_payload;
$apiKey = is_array($payload) ? trim((string) ($payload['api_key'] ?? '')) : '';
$endpoint = trim((string) $connection->endpoint);

if ($apiKey === '' || $endpoint === '') {
    echo "Connection '{$slug}' has no endpoint or API key stored.".PHP_EOL;

    exit(1);
}

$base = preg_replace('#/(?:v1/)?chat/completions$#', '', $endpoint);

$paths = [
    $base.'/models',
    $base.'/v1/models',
    preg_replace('#/api$#', '', $base).'/api/models',
    preg_replace('#/api$#', '', $base).'/v1/models',
];

echo "slug     : {$connection->slug}".PHP_EOL;
echo "endpoint : {$endpoint}".PHP_EOL;
echo "base     : {$base}".PHP_EOL;
echo 'key hint : …'.substr($apiKey, -4).PHP_EOL.PHP_EOL;

foreach (array_values(array_unique($paths)) as $path) {
    try {
        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->connectTimeout(10)
            ->acceptJson()
            ->get($path);
    } catch (Throwable $e) {
        echo "GET {$path}".PHP_EOL.'  → error: '.$e->getMessage().PHP_EOL.PHP_EOL;

        continue;
    }

    echo "GET {$path}".PHP_EOL.'  → HTTP '.$response->status().PHP_EOL;

    if (! $response->successful()) {
        echo '    '.preg_replace('/\s+/', ' ', mb_substr((string) $response->body(), 0, 160)).PHP_EOL.PHP_EOL;

        continue;
    }

    $json = $response->json();
    $ids = [];

    if (is_array($json)) {
        $rows = $json['data'] ?? $json['models'] ?? $json;
        foreach ((array) $rows as $row) {
            $id = is_array($row) ? ($row['id'] ?? $row['name'] ?? null) : (is_string($row) ? $row : null);
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }
    }

    if ($ids === []) {
        echo '    (no ids parsed) '.preg_replace('/\s+/', ' ', mb_substr((string) $response->body(), 0, 300)).PHP_EOL.PHP_EOL;

        continue;
    }

    sort($ids);
    echo '    '.count($ids).' model(s):'.PHP_EOL;
    foreach ($ids as $id) {
        echo '      - '.$id.PHP_EOL;
    }
    echo PHP_EOL;
}
