<?php

/**
 * Run every core package's own suite the way a consumer would.
 *
 * For each `packages/{slug}`:
 *
 *  1. copy the package (no `vendor`, no `.phpunit.cache`) to
 *     `{tmp}/silverspoon-standalone-{slug}/packages/{slug}`;
 *  2. symlink every sibling package into `{tmp}/…/repositories/`;
 *  3. add the sibling path repositories and the root's `vcs` entries to the
 *     copy's `composer.json` (a copy sits inside `packages/`, so a `packages/*`
 *     path repository would resolve the package under test to itself);
 *  4. `composer install --no-scripts` and `composer test` inside the copy.
 *
 * Exit 0 only when every package installs and its suite passes. Driven by the
 * `standalone-packages` CI job; runnable locally:
 *
 * ```
 * php scripts/run-standalone-package-suite.php
 * php scripts/run-standalone-package-suite.php notification platform
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

$domains = require $root.'/config/domains.php';

$slugs = [];

foreach ($domains as $entry) {
    $package = $entry['package'] ?? null;

    if (is_string($package)) {
        $slugs[substr($package, strpos($package, '/') + 1)] = true;
    }
}

$slugs = array_keys($slugs);
sort($slugs);

$requested = array_slice($argv, 1);

if ($requested !== []) {
    $slugs = array_values(array_intersect($slugs, $requested));

    if ($slugs === []) {
        fwrite(STDERR, 'No package matched: '.implode(', ', $requested)."\n");
        exit(1);
    }
}

$composer = standalone_composer_arguments();

$failures = [];
$passed = [];

foreach ($slugs as $slug) {
    if (! is_dir($root.'/packages/'.$slug.'/tests')) {
        continue;
    }

    echo "\n==> {$slug}\n";

    [$code, $output] = standalone_run($root, $slug, $composer);

    echo $code === 0 ? "    ok\n" : $output."\n";

    if ($code === 0) {
        $passed[] = $slug;
    } else {
        $failures[] = $slug;
    }
}

echo "\n".count($passed).' passed, '.count($failures)." failed\n";

if ($failures !== []) {
    fwrite(STDERR, 'Failed: '.implode(', ', $failures)."\n");
    exit(1);
}

exit(0);

/**
 * The Composer entry point, as an argv prefix for `proc_open`.
 *
 * @return list<string>
 */
function standalone_composer_arguments(): array
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
 * Copy, wire and run one package. Returns [exit code, combined output].
 *
 * @param  list<string>  $composer
 * @return array{int, string}
 */
function standalone_run(string $root, string $slug, array $composer): array
{
    $work = sys_get_temp_dir().DIRECTORY_SEPARATOR.'silverspoon-standalone-'.$slug;

    standalone_remove($work);

    $target = $work.DIRECTORY_SEPARATOR.'packages'.DIRECTORY_SEPARATOR.$slug;
    $repositories = $work.DIRECTORY_SEPARATOR.'repositories';

    standalone_copy($root.'/packages/'.$slug, $target);
    @mkdir($repositories, 0777, true);

    $domains = require $root.'/config/domains.php';
    $siblings = [];

    foreach ($domains as $entry) {
        $package = $entry['package'] ?? null;

        if (! is_string($package)) {
            continue;
        }

        $sibling = substr($package, strpos($package, '/') + 1);

        if ($sibling !== $slug) {
            $siblings[$sibling] = true;
        }
    }

    foreach (array_keys($siblings) as $sibling) {
        @symlink($root.'/packages/'.$sibling, $repositories.DIRECTORY_SEPARATOR.$sibling);
    }

    standalone_wire_manifest($root, $target, $slug, array_keys($siblings));

    [$installCode, $installOutput] = standalone_process(
        array_merge($composer, ['--working-dir='.$target, 'install', '--no-interaction', '--no-scripts', '--prefer-dist']),
        $target,
    );

    if ($installCode !== 0) {
        return [$installCode, "composer install failed:\n".standalone_tail($installOutput)];
    }

    $result = standalone_process(
        array_merge($composer, ['--working-dir='.$target, 'test', '--no-interaction']),
        $target,
    );

    standalone_remove($work);

    return $result;
}

/**
 * A manifest the copy can install from, written beside it.
 *
 * The copy lives inside the monolith, so Composer finds the monolith's own
 * `composer.json` and installs against it unless one sits closer. Adding only
 * `repositories` here keeps the host's entire dependency set (so the package is
 * installed as a dependency of the project that already requires it) and swaps
 * the `packages/*` path for the sibling symlinks, which is exactly what a
 * consumer's project does.
 */
function standalone_wire_manifest(string $root, string $target, string $slug, array $siblings): void
{
    $path = $target.DIRECTORY_SEPARATOR.'composer.json';
    $manifest = json_decode((string) file_get_contents($path), true);

    $repositories = [];

    $rootManifest = json_decode((string) file_get_contents($root.'/composer.json'), true);

    foreach ((array) ($rootManifest['repositories'] ?? []) as $repository) {
        if (($repository['type'] ?? null) === 'vcs') {
            $repositories[] = $repository;
        }
    }

    foreach ($siblings as $sibling) {
        $repositories[] = [
            'type' => 'path',
            'url' => '../../repositories/'.$sibling,
            'options' => ['symlink' => false],
        ];
    }

    $manifest['repositories'] = $repositories;
    $manifest['minimum-stability'] = 'dev';
    $manifest['prefer-stable'] = true;
    $manifest['config'] = (array) ($manifest['config'] ?? []) + ['allow-plugins' => true];

    file_put_contents(
        $path,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
    );
}

/**
 * @param  list<string>  $command
 * @return array{int, string}
 */
function standalone_process(array $command, string $directory): array
{
    $process = proc_open($command, [1 => ['file', 'php://stdout', 'w'], 2 => ['file', 'php://stderr', 'w']], $pipes, $directory, getenv() ?: null);

    if (! is_resource($process)) {
        return [1, 'could not start '.implode(' ', $command)];
    }

    return [proc_close($process), ''];
}

function standalone_copy(string $source, string $destination): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    @mkdir($destination, 0777, true);

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $target = $destination.DIRECTORY_SEPARATOR.$relative;

        if ($item->isDir()) {
            @mkdir($target, 0777, true);

            continue;
        }

        if (str_starts_with($relative, 'vendor'.DIRECTORY_SEPARATOR) || str_starts_with($relative, '.phpunit.cache')) {
            continue;
        }

        @mkdir(dirname($target), 0777, true);
        copy($item->getPathname(), $target);
    }
}

function standalone_remove(string $directory): void
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

function standalone_tail(string $output, int $lines = 40): string
{
    $split = preg_split('/\r?\n/', trim($output)) ?: [];

    return implode("\n", array_slice($split, -$lines));
}
