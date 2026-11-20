<?php

/**
 * Smoke-run `client-scaffold/` the way a consumer would.
 *
 * `PackageStandaloneRunTest` proves each package's own suite passes in isolation;
 * this proves the **template** assembles them. Neither check alone would catch a
 * scaffold whose registry names a route file the package does not ship, a loader
 * that stopped calling `packageApiRoutePaths()`, a provider missing from
 * `bootstrap/providers.php`, or a host-owned config the kernel reads but nobody
 * published.
 *
 * Steps, all inside `{tmp}/silverspoon-client-scaffold/`:
 *
 *  1. copy `client-scaffold` (no `vendor`, no `node_modules`);
 *  2. rewrite the copy's `composer.json` — path repositories to
 *     `packages/*` instead of the private VCS mirrors, `minimum-stability: dev`
 *     so the untagged path packages resolve — then `composer install`;
 *  3. `php artisan package:discover` and `php artisan domain:list`: the registry
 *     must report the ten core domains and no product domain;
 *  4. `php artisan route:list --json`: the pinned core URI set must be present;
 *  5. `php artisan migrate --force` on sqlite: host-owned migrations plus the
 *     package ones the providers load;
 *  6. one real request through the kernel: `POST /api/v1/auth/login` with an
 *     empty body must answer 422 with a translated sentence, never a raw
 *     validation key.
 *
 * Exit 0 only when every step holds. Runnable locally:
 *
 * ```
 * php -d memory_limit=1G scripts/run-consumer-scaffold-check.php
 * ```
 *
 * `COMPOSER_PHAR` overrides the Composer entry point when `composer.phar` is not
 * on `PATH`.
 */
$root = dirname(__DIR__);

if (! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Run `composer install` in the monolith first.\n");
    exit(1);
}

/**
 * Core URIs a consumer install must serve. One per domain, plus the kernel's
 * cross-package surface — the registry can name a package whose routes never
 * mount, and a route list is the only thing that notices.
 *
 * @var list<string>
 */
const SCAFFOLD_REQUIRED_URIS = [
    'api/v1/auth/login',
    'api/v1/users',
    'api/v1/navigation/menus',
    'api/v1/activity-logs',
    'api/v1/error-logs',
    'api/v1/currencies',
    'api/v1/services',
    'api/v1/invoices',
    'api/v1/inference/providers',
    'api/v1/ai/connections',
    'api/v1/admin/settings/groups',
    'api/v1/admin/domain-access',
    'api/v1/product-activations',
    'api/v1/batch',
];

/** @var list<string> */
$steps = [];

$work = sys_get_temp_dir().DIRECTORY_SEPARATOR.'silverspoon-client-scaffold';

scaffold_remove($work);
scaffold_copy($root.'/client-scaffold', $work);

$composer = scaffold_composer_arguments();

try {
    $steps[] = ['copy', true, $work];

    $steps[] = ['composer install', ...scaffold_composer_install($root, $work, $composer)];

    $steps[] = ['package:discover', ...scaffold_artisan($work, ['package:discover', '--ansi'])];

    $steps[] = ['domain:list', ...scaffold_domains($work)];

    $steps[] = ['route:list', ...scaffold_routes($work)];

    $steps[] = ['migrate', ...scaffold_migrate($work)];

    $steps[] = ['validation envelope', ...scaffold_validation_probe($work)];
} finally {
    scaffold_remove($work);
}

$failed = [];

foreach ($steps as $step) {
    [$name, $ok, $detail] = $step;

    echo ($ok ? '  ok   ' : '  FAIL ').$name."\n";

    if (! $ok) {
        echo '       '.str_replace("\n", "\n       ", trim((string) $detail))."\n";
        $failed[] = $name;
    }
}

echo "\n";

if ($failed !== []) {
    fwrite(STDERR, count($failed).' scaffold step(s) failed: '.implode(', ', $failed)."\n");
    exit(1);
}

echo "client-scaffold assembles the core packages.\n";
exit(0);

/**
 * The Composer entry point, as an argv prefix for `proc_open`.
 *
 * @return list<string>
 */
function scaffold_composer_arguments(): array
{
    $phar = (string) getenv('COMPOSER_PHAR');

    if ($phar === '' || ! is_file($phar)) {
        $phar = '';

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            if ($directory === '') {
                continue;
            }

            $candidate = rtrim($directory, '\\/').DIRECTORY_SEPARATOR.'composer.phar';

            if (is_file($candidate)) {
                $phar = $candidate;

                break;
            }
        }
    }

    if ($phar === '') {
        fwrite(STDERR, "Could not locate composer.phar — set COMPOSER_PHAR.\n");
        exit(1);
    }

    return [PHP_BINARY, $phar];
}

/**
 * A manifest the scaffold can install from, then `composer install`.
 *
 * The copy lives outside the monolith, so the private VCS mirrors are replaced by
 * path repositories to this checkout's `packages/`. `minimum-stability: dev` is
 * required: a path package carries its `version` (`1.0.3`) but Composer still
 * resolves it as a dev branch when the repository is `path` and the root has no
 * matching tag history.
 *
 * @param  list<string>  $composer
 * @return array{bool, string}
 */
function scaffold_composer_install(string $root, string $work, array $composer): array
{
    $manifestPath = $work.DIRECTORY_SEPARATOR.'composer.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true);

    if (! is_array($manifest)) {
        return [false, 'composer.json is not valid JSON.'];
    }

    $repositories = [];

    foreach ((array) ($manifest['repositories'] ?? []) as $repository) {
        if (($repository['type'] ?? null) !== 'vcs') {
            $repositories[] = $repository;
        }
    }

    // `pdf-tools` lives in its own private repo and is not one of the six core
    // packages this check assembles. Drop the requirement so the run needs no
    // org token: the scaffold's own composer.json keeps it for a real consumer.
    unset($manifest['require']['silverspoonmedia/pdf-tools']);

    // Likewise `spatie/laravel-pdf` is a product-only dependency. Not required by
    // the six core packages, so drop it from the temporary manifest rather than
    // force the network to fetch a PDF stack.
    unset($manifest['require']['spatie/laravel-pdf']);

    foreach (['platform', 'navigation', 'notification', 'audit', 'billing', 'ai'] as $slug) {
        $repositories[] = [
            'type' => 'path',
            'url' => $root.'/packages/'.$slug,
            'options' => ['symlink' => false],
        ];
    }

    $manifest['repositories'] = $repositories;
    // Stability stays `stable`: each path package pins its `version` (1.0.3), so a
    // `dev` floor would only widen the resolver's search for no gain.
    $manifest['prefer-stable'] = true;
    $manifest['config'] = array_merge(
        (array) ($manifest['config'] ?? []),
        ['allow-plugins' => true],
    );

    file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
    );

    return scaffold_process(
        array_merge($composer, ['--working-dir='.$work, 'install', '--no-interaction', '--no-scripts', '--prefer-dist']),
        $work,
    );
}

/**
 * `php artisan` inside the copy, with the environment a consumer would have.
 *
 * The scaffold ships no `.env`; every value the kernel needs at boot is supplied
 * here so the run does not depend on a developer's local file.
 *
 * @param  list<string>  $arguments
 * @return array{bool, string}
 */
function scaffold_artisan(string $work, array $arguments): array
{
    $environment = [
        'APP_ENV' => 'testing',
        'APP_KEY' => 'base64:2fl+KtvkdphvQyEfmNqD2aE7nKj8vR3xW5yZ1aB4cD8=',
        'APP_DEBUG' => 'true',
        'APP_URL' => 'http://localhost',
        'APP_LOCALE' => 'en',
        'APP_FALLBACK_LOCALE' => 'en',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $work.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'consumer.sqlite',
        'JWT_SECRET' => 'testing-jwt-secret-key-with-enough-length',
        'AUTH_GUARD' => 'api',
        'AUTH_DEFAULT_ROLE' => 'User',
        'BCRYPT_ROUNDS' => '4',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'array',
        'BROADCAST_CONNECTION' => 'null',
        'HCAPTCHA_SECRET' => '',
        'HCAPTCHA_SITEKEY' => '',
        'ENCRYPTION_KMS_ENABLED' => 'false',
        'ENCRYPTION_BLIND_INDEX_PEPPER' => 'consumer-scaffold-pepper',
        'ENCRYPTION_DECRYPTED_CACHE_MAX_TTL' => '60',
        'ENCRYPTION_SESSION_ENABLED' => 'false',
        'LOG_CHANNEL' => 'single',
    ];

    $command = array_merge(['php', 'artisan'], $arguments);

    return scaffold_process($command, $work, $environment);
}

/**
 * `domain:list` must report the ten core domains and no product domain.
 *
 * @return array{bool, string}
 */
function scaffold_domains(string $work): array
{
    $file = $work.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'consumer.sqlite';

    if (! is_file($file)) {
        touch($file);
    }

    [$ok, $output] = scaffold_artisan($work, ['domain:list', '--no-ansi']);

    if (! $ok) {
        return [false, $output];
    }

    $missing = [];
    $product = [];

    foreach (['Identity', 'Navigation', 'Notification', 'ActivityLog', 'ErrorLog', 'PaymentGateway', 'Currency', 'Service', 'Inference', 'Ai'] as $domain) {
        if (! str_contains($output, $domain)) {
            $missing[] = $domain;
        }
    }

    foreach (['Archivd', 'Microstock'] as $domain) {
        if (str_contains($output, $domain)) {
            $product[] = $domain;
        }
    }

    if ($missing !== []) {
        return [false, 'domain:list does not report: '.implode(', ', $missing)."\n".scaffold_tail($output)];
    }

    if ($product !== []) {
        return [false, 'the scaffold registry reports a product domain the install does not carry: '.implode(', ', $product)];
    }

    return [true, ''];
}

/**
 * Every pinned core URI resolves, and no product URI does.
 *
 * @return array{bool, string}
 */
function scaffold_routes(string $work): array
{
    if (! is_file($work.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'consumer.sqlite')) {
        touch($work.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'consumer.sqlite');
    }

    [$ok, $output] = scaffold_artisan($work, ['route:list', '--json']);

    if (! $ok) {
        return [false, $output];
    }

    // `artisan` may print a warning before the JSON payload; keep the object.
    $json = substr($output, (int) strpos($output, '['));
    $routes = json_decode($json, true);

    if (! is_array($routes)) {
        return [false, "route:list did not return JSON.\n".scaffold_tail($output)];
    }

    $uris = array_map(static fn (array $route): string => ltrim((string) ($route['uri'] ?? ''), '/'), $routes);
    $missing = array_values(array_diff(SCAFFOLD_REQUIRED_URIS, $uris));

    if ($missing !== []) {
        return [false, 'these core URIs did not mount: '."\n  - ".implode("\n  - ", $missing)];
    }

    // A product route in a consumer install means the registry or a loader still
    // names a domain the checkout does not ship.
    $product = [];

    foreach ($uris as $uri) {
        if (str_starts_with($uri, 'api/v1/archivd') || str_starts_with($uri, 'api/v1/microstock') || str_starts_with($uri, 'api/v1/admin/microstock')) {
            $product[] = $uri;
        }
    }

    if ($product !== []) {
        return [false, 'product routes mounted in a core-only install: '.implode(', ', array_slice($product, 0, 5))];
    }

    return [true, ''];
}

/**
 * The host-owned schema plus every package migration runs from scratch.
 *
 * @return array{bool, string}
 */
function scaffold_migrate(string $work): array
{
    return scaffold_artisan($work, ['migrate', '--force', '--no-interaction']);
}

/**
 * One real request: an empty login body must answer 422 with a sentence.
 *
 * A raw key (`validation.required`) in the body means the language files never
 * loaded — the failure a scaffold is most likely to ship, because it is
 * invisible to `route:list` and `migrate`.
 *
 * @return array{bool, string}
 */
function scaffold_validation_probe(string $work): array
{
    $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
], json_encode([]));
$response = $kernel->handle($request);
$body = (string) $response->getContent();
echo "\n__STATUS__".$response->getStatusCode()."__BODY__".$body;
PHP;

    $path = $work.DIRECTORY_SEPARATOR.'validation-probe.php';
    file_put_contents($path, "<?php\n\n".$script."\n");

    [$ok, $output] = scaffold_process(['php', 'validation-probe.php'], $work, scaffold_probe_environment($work));
    @unlink($path);

    if (! preg_match('/__STATUS__(\d+)__BODY__(.*)$/s', $output, $matches)) {
        return [false, "the validation probe produced no response.\n".scaffold_tail($output)];
    }

    $status = (int) $matches[1];
    $body = trim($matches[2]);

    if ($status !== 422) {
        return [false, "POST /api/v1/auth/login with an empty body answered {$status}, expected 422.\n".scaffold_tail($body)];
    }

    $decoded = json_decode($body, true);

    if (! is_array($decoded)) {
        return [false, 'the error envelope is not JSON: '.scaffold_tail($body)];
    }

    $message = (string) ($decoded['message'] ?? '');

    if ($message === '' || str_contains($message, 'validation.') || str_starts_with($message, 'api.')) {
        return [false, 'the 422 message is a raw translation key, not a sentence: '.$message];
    }

    return [true, ''];
}

/**
 * Environment for the plain-PHP fallback probe.
 *
 * @return array<string, string>
 */
function scaffold_probe_environment(string $work): array
{
    $sqlite = $work.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'consumer.sqlite';

    if (! is_file($sqlite)) {
        touch($sqlite);
    }

    return [
        'APP_ENV' => 'testing',
        'APP_KEY' => 'base64:2fl+KtvkdphvQyEfmNqD2aE7nKj8vR3xW5yZ1aB4cD8=',
        'APP_DEBUG' => 'false',
        'APP_URL' => 'http://localhost',
        'APP_LOCALE' => 'en',
        'APP_FALLBACK_LOCALE' => 'en',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $sqlite,
        'JWT_SECRET' => 'testing-jwt-secret-key-with-enough-length',
        'AUTH_GUARD' => 'api',
        'AUTH_DEFAULT_ROLE' => 'User',
        'BCRYPT_ROUNDS' => '4',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'array',
        'BROADCAST_CONNECTION' => 'null',
        'ENCRYPTION_KMS_ENABLED' => 'false',
        'ENCRYPTION_BLIND_INDEX_PEPPER' => 'consumer-scaffold-pepper',
    ];
}

/**
 * Run a command with the inherited environment plus overrides.
 *
 * @param  list<string>  $command
 * @param  array<string, string>  $environment
 * @return array{bool, string}
 */
function scaffold_process(array $command, string $directory, array $environment = []): array
{
    $environment = array_merge(getenv() ?: [], $environment);

    // stderr is redirected into the stdout pipe: reading the two descriptors one
    // after the other deadlocks as soon as the child fills the buffer of the
    // descriptor being read second, and Composer writes megabytes to stderr.
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
        $directory,
        $environment,
    );

    if (! is_resource($process)) {
        return [false, 'could not start '.implode(' ', $command)];
    }

    $output = stream_get_contents($pipes[1]) ?: '';

    fclose($pipes[1]);

    $code = proc_close($process);

    return [$code === 0, $output];
}

/**
 * Copy a tree, skipping an installed `vendor`, build output and caches.
 */
function scaffold_copy(string $source, string $destination): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    @mkdir($destination, 0777, true);

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);

        if ($item->isDir()) {
            if (in_array(strtok($relative, DIRECTORY_SEPARATOR), ['vendor', 'node_modules', 'storage'], true)) {
                continue;
            }

            @mkdir($destination.DIRECTORY_SEPARATOR.$relative, 0777, true);

            continue;
        }

        if (in_array(strtok($relative, DIRECTORY_SEPARATOR), ['vendor', 'node_modules'], true)) {
            continue;
        }

        @mkdir(dirname($destination.DIRECTORY_SEPARATOR.$relative), 0777, true);
        copy($item->getPathname(), $destination.DIRECTORY_SEPARATOR.$relative);
    }

    // The scaffold ignores `storage/`, so a fresh checkout ships no skeleton:
    // the kernel's `ensureStorageDirectories()` covers the framework paths, and
    // `storage/logs` has to exist before the first artisan call opens a log.
    @mkdir($destination.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs', 0777, true);
    @mkdir($destination.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'data', 0777, true);
    @mkdir($destination.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'sessions', 0777, true);
    @mkdir($destination.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'views', 0777, true);
    @mkdir($destination.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache', 0777, true);

    $database = $destination.DIRECTORY_SEPARATOR.'database';

    @mkdir($database, 0777, true);
    touch($database.DIRECTORY_SEPARATOR.'consumer.sqlite');
}

function scaffold_remove(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir() && ! $item->isLink()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($directory);
}

function scaffold_tail(string $output, int $lines = 30): string
{
    $split = preg_split('/\r?\n/', trim($output)) ?: [];

    return implode("\n", array_slice($split, -$lines));
}
