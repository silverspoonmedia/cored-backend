<?php

/**
 * Shared plumbing for the permanent locale guards in `scripts/`.
 *
 * Every guard here answers a question that spans three surfaces — the backend PHP
 * namespaces (`lang/{locale}/**`), the backend flat pack (`lang/{locale}.json`), and
 * the sibling SPA pack (`monolith-vue-workers/src/i18n/locales/*.json`) — and every
 * one of them is a gate (exit 1 on a finding). Two rules are enforced centrally:
 *
 *   - **The sibling repo is required, not optional.** The earlier guards reported
 *     `0 divergent` when `monolith-vue-workers` was absent, because a missing file
 *     was recorded as an `error` entry that no counter ever saw. A guard that cannot
 *     see half its input must fail loudly: `locale_scan_require_spa()`.
 *   - **One flattening implementation.** A guard that walks arrays its own way
 *     reports findings the other guards cannot reproduce.
 *
 * These are plain functions, not a class: the guards are standalone scripts run by
 * `php scripts/…` and by `LocaleGuardTest`, and a `require` keeps them dependency-free.
 */

/**
 * Absolute path to the backend repo root (this file lives in `scripts/lib/`).
 */
function locale_scan_root(): string
{
    return dirname(__DIR__, 2);
}

/**
 * Absolute path to the sibling SPA repo, whether or not it exists.
 */
function locale_scan_spa_root(): string
{
    return dirname(locale_scan_root()).'/monolith-vue-workers';
}

/**
 * Locales the application supports, reference locale first.
 *
 * @return array<int, string>
 */
function locale_scan_supported(): array
{
    return ['en', 'id', 'ja', 'ko', 'zh_CN', 'ar', 'ru'];
}

/**
 * The locale's spelling inside the SPA, where the region separator is a hyphen.
 */
function locale_scan_spa_locale(string $locale): string
{
    return $locale === 'zh_CN' ? 'zh-CN' : $locale;
}

/**
 * Absolute path to a locale's SPA pack.
 */
function locale_scan_spa_file(string $spaRoot, string $locale): string
{
    return $spaRoot.'/src/i18n/locales/'.locale_scan_spa_locale($locale).'.json';
}

/**
 * Can the guards actually read the sibling SPA repo?
 *
 * The single definition of that question: the checkout exists **and** the reference
 * pack is readable. Both halves matter — a directory that exists but is empty (a
 * failed or partial checkout) cannot be compared against, and `is_dir()` alone would
 * call that present. `locale_scan_require_spa()` and `LocaleGuardTest` both ask this
 * function, so neither can drift into a weaker check.
 */
function locale_scan_spa_present(?string $spaRoot = null): bool
{
    $spaRoot ??= locale_scan_spa_root();

    return is_dir($spaRoot) && is_file($spaRoot.'/src/i18n/locales/en.json');
}

/**
 * Fail the guard when the sibling repo is not checked out.
 *
 * Without this, every cross-repo comparison silently compares against nothing and
 * exits 0 — the "green by absence" failure this helper exists to prevent.
 */
function locale_scan_require_spa(string $spaRoot): void
{
    if (locale_scan_spa_present($spaRoot)) {
        return;
    }

    fwrite(STDERR, 'cannot see the SPA repo: '.$spaRoot.PHP_EOL);
    fwrite(STDERR, 'This guard compares the backend packs with the SPA packs, so a run without it proves nothing.'.PHP_EOL);
    fwrite(STDERR, 'Check out monolith-vue-workers as a sibling of this repo and retry.'.PHP_EOL);

    exit(1);
}

/**
 * Flat dotted path => scalar value.
 *
 * @param  array<array-key, mixed>  $data
 * @return array<string, string>
 */
function locale_scan_flatten(array $data, string $prefix = ''): array
{
    $out = [];

    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $out += locale_scan_flatten($value, $path);
        } elseif (is_string($value)) {
            $out[$path] = $value;
        }
    }

    return $out;
}

/**
 * Flatten a PHP lang file or a JSON pack. Missing files flatten to nothing, which
 * is why every caller that needs to notice an absent file checks `is_file` first.
 *
 * @return array<string, string>
 */
function locale_scan_flatten_file(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    $data = str_ends_with($path, '.json')
        ? json_decode((string) file_get_contents($path), true)
        : include $path;

    return is_array($data) ? locale_scan_flatten($data) : [];
}

/**
 * Every `lang/{locale}/**` PHP file, as `lang/{locale}/relative/name.php` => path.
 *
 * @return array<string, string>
 */
function locale_scan_php_files(string $root, string $locale): array
{
    $base = $root.'/lang/'.$locale;
    $files = [];

    if (! is_dir($base)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $files[$relative] = $file->getPathname();
    }

    ksort($files);

    return $files;
}

/**
 * The backend surfaces of a locale: every PHP namespace file plus the flat pack.
 *
 * @return array<string, string> label => absolute path
 */
function locale_scan_backend_surfaces(string $root, string $locale): array
{
    $surfaces = locale_scan_php_files($root, $locale);

    $surfaces['lang/'.$locale.'.json'] = $root.'/lang/'.$locale.'.json';

    return $surfaces;
}

/**
 * The three surfaces a locale lives on, in report order: backend namespaces, the
 * backend flat pack, then the SPA pack.
 *
 * @return array<string, string> label => absolute path
 */
function locale_scan_surfaces(string $root, string $spaRoot, string $locale): array
{
    $surfaces = locale_scan_backend_surfaces($root, $locale);

    $surfaces['spa:src/i18n/locales/'.locale_scan_spa_locale($locale).'.json'] = locale_scan_spa_file($spaRoot, $locale);

    return $surfaces;
}

/**
 * Every key a locale can resolve, in both spellings Laravel accepts.
 *
 * A PHP namespace file contributes `namespace.dotted.key` — `lang/{locale}/api/activity-log.php`
 * plus `events.created` gives `api/activity-log.events.created` — and the flat pack
 * contributes its own bare keys. Both namespace separators are registered, because
 * `__('api/activity-log.events.created')` and `__('api.activity-log.events.created')`
 * resolve to the same value.
 *
 * @return array<string, true>
 */
function locale_scan_backend_keys(string $root, string $locale): array
{
    $known = [];

    foreach (locale_scan_php_files($root, $locale) as $label => $path) {
        $namespace = str_replace(['lang/'.$locale.'/', '.php'], ['', ''], $label);

        foreach (array_keys(locale_scan_flatten_file($path)) as $key) {
            $known[$namespace.'.'.$key] = true;
            $known[str_replace('/', '.', $namespace).'.'.$key] = true;
        }
    }

    foreach (array_keys(locale_scan_flatten_file($root.'/lang/'.$locale.'.json')) as $key) {
        $known[$key] = true;
    }

    return $known;
}

/**
 * Split `argv` into the requested locales and the `--flag` list.
 *
 * @param  array<int, string>  $argv
 * @return array{0: array<int, string>, 1: array<int, string>}
 */
function locale_scan_args(array $argv): array
{
    $args = array_slice($argv, 1);
    $requested = [];
    $flags = [];

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--')) {
            $flags[] = $arg;
        } else {
            $requested[] = $arg;
        }
    }

    return [$requested, $flags];
}

/**
 * Resolve a `--flag=value` argument, or null.
 *
 * @param  array<int, string>  $flags
 */
function locale_scan_flag_value(array $flags, string $name): ?string
{
    foreach ($flags as $flag) {
        if (str_starts_with($flag, $name.'=')) {
            return substr($flag, strlen($name) + 1);
        }
    }

    return null;
}

/**
 * Narrow the supported list to what the caller asked for, or exit 1 when nothing
 * they asked for is known — silently scanning everything would hide a typo.
 *
 * @param  array<int, string>  $known
 * @param  array<int, string>  $requested
 * @return array<int, string>
 */
function locale_scan_select(array $known, array $requested, string $what): array
{
    if ($requested === []) {
        return $known;
    }

    $selected = array_values(array_intersect($known, $requested));

    if ($selected === []) {
        fwrite(STDERR, 'no known '.$what.' in: '.implode(', ', $requested).PHP_EOL);
        exit(1);
    }

    return $selected;
}

/**
 * Shape test only: is this value plausibly a key path?
 *
 * Resolution decides whether a value that has this shape is a leak, but the shape
 * test keeps prose out of the resolution lookup. A key path is lowercase,
 * separator-joined, at least two segments, and carries no character that belongs to a
 * sentence. Both namespace separators are accepted, since a leaked key can be pasted
 * in either form.
 *
 * `youtube.com` and `Routing_Price.yaml` have the same shape, so a final segment that
 * is a known domain or file extension is rejected: that is a host or a filename, not
 * a key.
 */
function locale_scan_looks_like_key(string $value): bool
{
    $value = trim($value);

    if (! preg_match('#^[a-z][a-z0-9_-]*(?:[/.][a-z0-9_-]+)+$#', $value)) {
        return false;
    }

    $last = substr(strrchr(str_replace('/', '.', $value), '.') ?: '', 1);

    return ! in_array($last, [
        'com', 'tv', 'org', 'net', 'io', 'dev', 'id', 'co', 'me', 'gg', 'app',
        'yaml', 'yml', 'json', 'php', 'md', 'txt', 'csv', 'env', 'js', 'ts', 'vue',
    ], true);
}

/**
 * Does this key resolve against a set of known keys, in either namespace separator?
 *
 * @param  array<string, true>  $known
 */
function locale_scan_resolves(string $key, array $known): bool
{
    $key = trim($key);

    return isset($known[$key]) || isset($known[str_replace('/', '.', $key)]);
}

/**
 * Write the machine-readable worklist, when the caller asked for one.
 *
 * @param  array<string, mixed>  $payload
 */
function locale_scan_write_json(?string $path, array $payload): void
{
    if ($path === null) {
        return;
    }

    file_put_contents($path, json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL);
}
