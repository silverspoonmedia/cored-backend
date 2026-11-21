<?php

// Usage: php scripts/locale-report-query.php <locale> [<locale2> ...] [--verbose] [--file=<substr>]
//        php scripts/locale-report-query.php --json-diff=<locale>  (en.json keys missing in <locale>.json)

$args0 = array_slice($argv, 1);
foreach ($args0 as $a) {
    if (str_starts_with($a, '--json-show=')) {
        $parts = explode(':', substr($a, 12), 2);
        [$loc, $group] = $parts;
        $j = json_decode(file_get_contents(__DIR__."/../lang/{$loc}.json"), true);
        echo json_encode($j[$group] ?? null, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
        exit(0);
    }
    if (str_starts_with($a, '--json-diff=')) {
        $loc = substr($a, 12);
        $en = json_decode(file_get_contents(__DIR__.'/../lang/en.json'), true);
        $other = json_decode(file_get_contents(__DIR__."/../lang/{$loc}.json"), true);
        $missing = array_diff(array_keys($en), array_keys($other));
        $extra = array_diff(array_keys($other), array_keys($en));
        echo 'en=', count($en), ' ', $loc, '=', count($other), ' missing=', count($missing), ' extra=', count($extra), PHP_EOL;
        foreach ($missing as $m) {
            echo 'MISSING ', $m, PHP_EOL;
        }
        exit(0);
    }
}

$reportPath = __DIR__.'/../backend-locale-report.json';

if (! is_file($reportPath)) {
    fwrite(STDERR, "backend-locale-report.json is missing — it is generated, not committed.\n");
    fwrite(STDERR, "Run: php scripts/backend-locale-audit.php backend-locale-report.json\n");
    exit(1);
}

$report = json_decode(file_get_contents($reportPath), true);
$args = array_slice($argv, 1);
$verbose = in_array('--verbose', $args, true);
$fileFilter = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $fileFilter = substr($arg, 7);
    }
}
$locales = array_values(array_filter($args, fn ($a) => ! str_starts_with($a, '--')));

foreach ($report['files'] ?? [] as $file => $data) {
    if ($fileFilter !== null && ! str_contains($file, $fileFilter)) {
        continue;
    }
    $byLocale = [];
    foreach ($data['copy_candidates'] ?? [] as $row) {
        $byLocale[$row['locale']][] = $row;
    }
    foreach ($locales as $locale) {
        $rows = $byLocale[$locale] ?? [];
        if ($rows === []) {
            continue;
        }
        echo $locale, "\t", $file, "\t", count($rows), PHP_EOL;
        if ($verbose) {
            foreach ($rows as $row) {
                echo '  ', $row['key'], ' = ', $row['english'], PHP_EOL;
            }
        }
    }
}
