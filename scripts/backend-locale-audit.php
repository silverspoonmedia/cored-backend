<?php

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/backend-locale-audit.php <output.json>\n");
    exit(1);
}

$root = dirname(__DIR__);
$locales = ['en', 'id', 'ja', 'ko', 'zh_CN', 'ar', 'ru'];
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

function isLikelyCopy(string $key, mixed $value, array $excludedIdentifierKeys): bool
{
    if (! is_string($value) || trim($value) === '' || ! preg_match('/[A-Za-z]/', $value)) {
        return false;
    }

    $segments = preg_split('/[.\\\\]/', strtolower($key)) ?: [];
    foreach ($segments as $segment) {
        if (in_array($segment, $excludedIdentifierKeys, true)) {
            return false;
        }
    }

    if (preg_match('/^[A-Za-z0-9_.:\/-]+$/', $value) && ! preg_match('/\s/', $value)) {
        return false;
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

$reference = 'en';
$files = applicationFiles($reference, $managed, $root);
$report = [
    'locales' => $locales,
    'reference' => $reference,
    'managed_files_excluded' => $managed,
    'files' => [],
    'totals' => [
        'copy_candidates' => 0,
        'missing_keys' => 0,
        'extra_keys' => 0,
    ],
];

foreach ($files as $relative) {
    $referenceValues = [];
    flattenValues(include $root.'/lang/'.$reference.'/'.$relative, '', $referenceValues);
    $entry = [
        'copy_candidates' => [],
        'missing_keys' => [],
        'extra_keys' => [],
    ];

    foreach ($locales as $locale) {
        if ($locale === $reference) {
            continue;
        }

        $path = $root.'/lang/'.$locale.'/'.$relative;
        if (! is_file($path)) {
            $entry['missing_file'] ??= [];
            $entry['missing_file'][] = $locale;
            continue;
        }

        $values = [];
        flattenValues(include $path, '', $values);
        foreach (array_keys($referenceValues) as $key) {
            if (! array_key_exists($key, $values)) {
                $entry['missing_keys'][] = ['locale' => $locale, 'key' => $key];
                continue;
            }

            if (isLikelyCopy($key, $referenceValues[$key], $excludedIdentifierKeys)
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
