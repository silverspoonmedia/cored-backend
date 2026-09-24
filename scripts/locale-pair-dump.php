<?php

// Usage:
//   php scripts/locale-pair-dump.php <locale> [options]
//
// Read-only review dump for one backend locale. Prints TAB-separated rows so a
// translation wave can be reviewed key by key against the English source:
//
//   <namespace>\t<key>\t<en>\t<locale>
//
// `namespace` is the lang file path with `.php` stripped and `/` turned into `.`,
// so `web/archivd.php` + `microstock.describe_visible_options` becomes
// `web.archivd` + `microstock.describe_visible_options`.
//
// Options:
//   --file=<substr>  only files whose relative path contains this substring
//   --ns=<prefix>    only keys under this namespace prefix (e.g. web.payments)
//   --identical      only keys whose locale value is exactly the English value
//   --missing        only keys absent from the locale file
//   --count          print per-file counts instead of rows
//   --limit=<n>      cap the number of rows
//
// Managed (laravel-lang) files are included: they are still user-facing, and the
// wave needs to see them even though LocaleParityTest excludes them.
$root = dirname(__DIR__);
$locales = ['en', 'id', 'ja', 'ko', 'zh_CN', 'ar', 'ru'];

$args = array_slice($argv, 1);
$locale = null;
$fileFilter = null;
$nsFilter = null;
$onlyIdentical = false;
$onlyMissing = false;
$countMode = false;
$limit = 0;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $fileFilter = substr($arg, 7);
    } elseif (str_starts_with($arg, '--ns=')) {
        $nsFilter = substr($arg, 5);
    } elseif ($arg === '--identical') {
        $onlyIdentical = true;
    } elseif ($arg === '--missing') {
        $onlyMissing = true;
    } elseif ($arg === '--count') {
        $countMode = true;
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = (int) substr($arg, 8);
    } elseif (! str_starts_with($arg, '--')) {
        $locale = $arg;
    }
}

if ($locale === null || ! in_array($locale, $locales, true)) {
    fwrite(STDERR, 'usage: php scripts/locale-pair-dump.php <locale> [--file=<substr>] [--ns=<prefix>] [--identical] [--missing] [--count] [--limit=<n>]'.PHP_EOL);
    fwrite(STDERR, 'locales: '.implode(', ', $locales).PHP_EOL);
    exit(1);
}

if ($locale === 'en') {
    fwrite(STDERR, 'en is the reference locale; pick a target locale.'.PHP_EOL);
    exit(1);
}

/**
 * @return array<string, string> dot-notation key => value
 */
function flattenLangFile(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    $data = include $path;

    if (! is_array($data)) {
        return [];
    }

    $out = [];

    $walk = function (array $node, string $prefix) use (&$walk, &$out): void {
        foreach ($node as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $walk($value, $dotted);

                continue;
            }

            $out[$dotted] = (string) $value;
        }
    };

    $walk($data, '');

    return $out;
}

/**
 * @return array<int, string>
 */
function langFiles(string $base): array
{
    if (! is_dir($base)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    );

    $files = [];

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
        }
    }

    sort($files);

    return $files;
}

$referenceBase = $root.'/lang/en';
$targetBase = $root.'/lang/'.$locale;
$rows = [];
$counts = [];

foreach (langFiles($referenceBase) as $relative) {
    if ($fileFilter !== null && ! str_contains($relative, $fileFilter)) {
        continue;
    }

    $namespace = str_replace('/', '.', substr($relative, 0, -4));

    if ($nsFilter !== null && $namespace !== $nsFilter && ! str_starts_with($namespace, $nsFilter.'.')) {
        continue;
    }

    $reference = flattenLangFile($referenceBase.'/'.$relative);
    $target = flattenLangFile($targetBase.'/'.$relative);

    foreach ($reference as $key => $english) {
        if (trim($english) === '') {
            continue;
        }

        $has = array_key_exists($key, $target);
        $value = $has ? $target[$key] : null;
        $missing = ! $has || trim((string) $value) === '';
        $identical = ! $missing && $value === $english;

        if ($onlyMissing && ! $missing) {
            continue;
        }

        if ($onlyIdentical && ! $identical) {
            continue;
        }

        $counts[$namespace] = ($counts[$namespace] ?? 0) + 1;

        if (! $countMode) {
            $rows[] = $namespace."\t".$key."\t".$english."\t".($missing ? '<MISSING>' : $value);
        }
    }
}

if ($countMode) {
    ksort($counts);

    foreach ($counts as $namespace => $count) {
        echo $namespace, "\t", $count, PHP_EOL;
    }

    echo 'TOTAL', "\t", array_sum($counts), PHP_EOL;
} else {
    $shown = $limit > 0 ? array_slice($rows, 0, $limit) : $rows;

    echo implode(PHP_EOL, $shown), PHP_EOL;
    fwrite(STDERR, 'rows='.count($rows).' shown='.count($shown).' locale='.$locale.PHP_EOL);
}
