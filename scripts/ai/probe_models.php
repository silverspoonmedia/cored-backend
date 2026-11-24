<?php

/**
 * Read-only probe: which model aliases does the stored AI connection accept?
 *
 * Sends a 1-token chat-completions request per candidate model and prints the
 * HTTP status. 200 = alias exists; 400 = unknown model / bad request.
 * The API key is never printed.
 *
 * Usage: php scripts/ai/probe_models.php [slug]
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

    echo 'Usage: php scripts/ai/probe_models.php <slug> [model…]'.PHP_EOL;
    echo $slugs === []
        ? 'No ai_connections rows stored yet.'.PHP_EOL
        : 'Stored connections: '.implode(', ', $slugs).PHP_EOL;

    exit(1);
}

// Read the row directly: the resolver refuses `pending` rows on purpose, and
// diagnosing a pending row is exactly the point of this script.
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

echo "slug     : {$connection->slug} (status: {$connection->status->value})".PHP_EOL;
echo "endpoint : {$endpoint}".PHP_EOL;
echo 'key hint : …'.substr($apiKey, -4).PHP_EOL.PHP_EOL;

$models = array_values(array_filter(array_unique(array_merge(
    array_slice($argv, 2),
    [$connection->test_model],
))));

echo str_pad('model', 32).' status  body'.PHP_EOL;
echo str_repeat('-', 90).PHP_EOL;

foreach ($models as $model) {
    $started = microtime(true);

    try {
        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->connectTimeout(10)
            ->acceptJson()
            ->withHeaders(is_array($connection->extra_headers) ? $connection->extra_headers : [])
            ->post($endpoint, [
                'model' => $model,
                'stream' => false,
                'max_tokens' => 1,
                'temperature' => 0.0,
                'messages' => [['role' => 'user', 'content' => 'ping']],
            ]);

        $status = $response->status();
        $body = $response->successful()
            ? 'OK ('.round((microtime(true) - $started) * 1000).' ms)'
            : preg_replace('/\s+/', ' ', mb_substr((string) $response->body(), 0, 120));
    } catch (Throwable $e) {
        $status = 'ERR';
        $body = preg_replace('/\s+/', ' ', mb_substr($e->getMessage(), 0, 120));
    }

    echo str_pad($model, 32).' '.str_pad((string) $status, 7).' '.$body.PHP_EOL;
}
