<?php

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/backend-locale-audit.php <output.json>\n");
    exit(1);
}

$root = dirname(__DIR__);
require __DIR__.'/lib/locale-scan-support.php';

// One source of truth; a literal list here outlives the locales it names.
$locales = locale_scan_supported();
$managed = [
    'actions.php',
    'auth.php',
    'http-statuses.php',
    'pagination.php',
    'passwords.php',
    'validation.php',
];
$excludedIdentifierKeys = [
    'code',
    'currency',
    'currency_code',
    'provider',
    'provider_slug',
    'route',
    'slug',
    'status',
    'type',
    'kind',
    'role',
    'permission',
];

/**
 * Flat-pack scalars that are identical to English in every locale by design:
 * universal acronyms and protocol words that no locale translates. The SPA
 * duplicate-English rule carries the same list globally
 * (`scripts/lib/duplicate-english-allowlist.mjs` → `globalDuplicateEnglishKeys`),
 * so `OK` is allowed there and must be allowed here too — the flat pack is the
 * same `laravel-lang` chrome on the backend side. Prefer a translated value;
 * add an entry only for a word that is the accepted form everywhere.
 *
 * @var array<int, string>
 */
$universalFlatPackScalars = [
    'OK',
];

function flattenValues(mixed $value, string $prefix = '', array &$out = []): array
{
    if (is_array($value)) {
        foreach ($value as $key => $child) {
            flattenValues($child, $prefix === '' ? (string) $key : $prefix.'.'.$key, $out);
        }

        return $out;
    }

    $out[$prefix] = $value;

    return $out;
}

/**
 * `$strictSingleToken` exists for the flat package packs. The default heuristic
 * deliberately ignores single-token values because in the app namespaces those
 * are brands, identifiers and enum-like labels that are *supposed* to stay in
 * English. The packs are different: they are package chrome prose, so a lone
 * English word there really is an untranslated string (`ko.json` shipped `New`
 * for four waves precisely because the single-token rule waved it through).
 */
function isLikelyCopy(string $key, mixed $value, array $excludedIdentifierKeys, bool $strictSingleToken = false): bool
{
    global $universalFlatPackScalars;

    if (! is_string($value) || trim($value) === '' || ! preg_match('/[A-Za-z]/', $value)) {
        return false;
    }

    // Universal acronyms (`OK`) are the accepted form in every locale, so the
    // English-identical value is the translation, not a missing one.
    if (in_array(trim($value), $universalFlatPackScalars, true)) {
        return false;
    }

    // Format examples are not prose: an input placeholder such as
    // `UC… / @handle / https://youtube.com/@handle` is the same sample in every
    // locale by design, so it is never an untranslated string.
    if (str_contains($value, '://') || str_contains($value, '@handle')) {
        return false;
    }

    $segments = preg_split('/[.\\\\]/', strtolower($key)) ?: [];
    foreach ($segments as $segment) {
        if (in_array($segment, $excludedIdentifierKeys, true)) {
            return false;
        }
    }

    if (preg_match('/^[A-Za-z0-9_.:\/-]+$/', $value) && ! preg_match('/\s/', $value)) {
        return $strictSingleToken;
    }

    return true;
}

function applicationFiles(string $locale, array $managed, string $root): array
{
    $base = $root.'/lang/'.$locale;
    if (! is_dir($base)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
        if (str_contains($relative, '/') === false && in_array($relative, $managed, true)) {
            continue;
        }

        $files[] = $relative;
    }
    sort($files);

    return $files;
}

/**
 * Flat laravel-lang package packs live at `lang/{locale}.json`, not under
 * `lang/{locale}/`, so the directory walk above never saw them. They are still
 * user-facing (Laravel consults the JSON pack before the PHP namespaces for any
 * key without a `/`), and leaving them out of the audit is how `ko.json` kept an
 * untranslated `New` through four curation waves. They are reported under the
 * reference pack name so the entry key is stable across locales.
 */
function flatPackFiles(string $reference, string $root): array
{
    return is_file($root.'/lang/'.$reference.'.json') ? [$reference.'.json'] : [];
}

/**
 * @return array<string, mixed>
 */
function loadValues(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    if (str_ends_with($path, '.json')) {
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    $data = include $path;

    return is_array($data) ? $data : [];
}

/**
 * Resolve the on-disk path for a report entry: a `.json` entry is a flat pack at
 * `lang/{locale}.json`, anything else is a file under `lang/{locale}/`.
 */
function localePath(string $root, string $locale, string $relative): string
{
    return str_ends_with($relative, '.json')
        ? $root.'/lang/'.$locale.'.json'
        : $root.'/lang/'.$locale.'/'.$relative;
}

$reference = 'en';
$files = array_merge(applicationFiles($reference, $managed, $root), flatPackFiles($reference, $root));
$report = [
    'locales' => $locales,
    'reference' => $reference,
    'managed_files_excluded' => $managed,
    'flat_packs_included' => flatPackFiles($reference, $root),
    'files' => [],
    'totals' => [
        'copy_candidates' => 0,
        'missing_keys' => 0,
        'extra_keys' => 0,
    ],
];

foreach ($files as $relative) {
    $referenceValues = [];
    flattenValues(loadValues(localePath($root, $reference, $relative)), '', $referenceValues);
    $entry = [
        'copy_candidates' => [],
        'missing_keys' => [],
        'extra_keys' => [],
    ];

    foreach ($locales as $locale) {
        if ($locale === $reference) {
            continue;
        }

        $path = localePath($root, $locale, $relative);
        if (! is_file($path)) {
            $entry['missing_file'] ??= [];
            $entry['missing_file'][] = $locale;

            continue;
        }

        $values = [];
        flattenValues(loadValues($path), '', $values);
        foreach (array_keys($referenceValues) as $key) {
            if (! array_key_exists($key, $values)) {
                $entry['missing_keys'][] = ['locale' => $locale, 'key' => $key];

                continue;
            }

            if (isLikelyCopy($key, $referenceValues[$key], $excludedIdentifierKeys, str_ends_with($relative, '.json'))
                && $values[$key] === $referenceValues[$key]
            ) {
                $entry['copy_candidates'][] = [
                    'locale' => $locale,
                    'key' => $key,
                    'english' => $referenceValues[$key],
                ];
            }
        }

        foreach (array_keys($values) as $key) {
            if (! array_key_exists($key, $referenceValues)) {
                $entry['extra_keys'][] = ['locale' => $locale, 'key' => $key];
            }
        }
    }

    if ($entry['copy_candidates'] !== [] || $entry['missing_keys'] !== [] || $entry['extra_keys'] !== [] || isset($entry['missing_file'])) {
        $report['files'][$relative] = $entry;
    }
    $report['totals']['copy_candidates'] += count($entry['copy_candidates']);
    $report['totals']['missing_keys'] += count($entry['missing_keys']);
    $report['totals']['extra_keys'] += count($entry['extra_keys']);
}

$output = $root.'/'.$argv[1];
file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

echo json_encode($report['totals'], JSON_UNESCAPED_UNICODE).PHP_EOL;
