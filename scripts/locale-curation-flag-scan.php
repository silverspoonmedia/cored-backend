<?php

// Curation-flag scan — a `// curated at …` comment with no key under it.
//
// The flag is the record that a human or agent read the consuming surface before
// rewriting the value, so it belongs to exactly one key. Delete the key and the flag
// survives as a comment about nothing: `lang/*/web/settings.php` and
// `lang/*/api/notifications.php` kept 132 such lines after the 2026-10-07 product
// domain sunset, and every locale gate stayed green while they sat there. That is the
// blind spot this guard closes — the flag is not a key, so parity compares nothing,
// the prose scan strips comments before it reads values, and the usage test only walks
// call sites. Nothing in the corpus was watching the comments themselves.
//
// A flag is a finding when the next non-empty line is not a key line
// (`'name' =>`), which covers both shapes the corpus uses: a leaf and a group opener
// (`'groups' => [`). A flag that is the last non-empty line of the file is a finding
// for the same reason.
//
// A flag repeated on the *same* key is deliberately not a finding: the corpus carries
// those from consecutive curation waves (`apply-locale-translations.php` replaces the
// flag above the key it touches, so the next touch collapses a stack), and they are an
// artefact of the tooling rather than residue of a deletion. Reporting them would put
// this gate permanently red for a defect no deletion produced.
//
// Scope is the backend PHP namespaces: `lang/{locale}/**/*.php`. The flat pack
// `lang/{locale}.json` cannot carry comments, and the SPA's equivalent record is a
// JSON sidecar validated by `check-curation-log.mjs`, so neither is this guard's input.
//
// Usage:
//   php scripts/locale-curation-flag-scan.php              all locales
//   php scripts/locale-curation-flag-scan.php ko ru        only these locales
//   php scripts/locale-curation-flag-scan.php --verbose    per-file detail
//   php scripts/locale-curation-flag-scan.php --json=out.json  also write the report
//
// Exit code is 0 only when no locale has an orphaned flag, so this doubles as a gate.
require __DIR__.'/lib/locale-scan-support.php';

$root = locale_scan_root();

$locales = locale_scan_supported();

[$requested, $flags] = locale_scan_args($argv);
$verbose = in_array('--verbose', $flags, true);
$jsonOut = locale_scan_flag_value($flags, '--json');
$locales = locale_scan_select($locales, $requested, 'locale');

// The flag marker, exactly as `apply-locale-translations.php` writes it. Matched after
// `ltrim()`, because the indentation is the key's nesting depth, not part of the flag.
const FLAG_PREFIX = '// curated at ';

// A key line: `'name' =>`, at any indentation, opening a leaf or a nested group.
const KEY_LINE = "/^\\s*'[^']+'\\s*=>/";

/**
 * Orphaned flags in one file, as `line number => reason`.
 *
 * @return array<int, string>
 */
function curationFlagFindings(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        return [];
    }

    $findings = [];
    $count = count($lines);

    for ($i = 0; $i < $count; $i++) {
        if (! str_starts_with(ltrim($lines[$i]), FLAG_PREFIX)) {
            continue;
        }

        $next = $i + 1;

        // Skip blank lines and any flag lines stacked on the same key, so a stack is
        // judged by the key its last flag sits above.
        while (true) {
            while ($next < $count && trim($lines[$next]) === '') {
                $next++;
            }

            if ($next < $count && str_starts_with(ltrim($lines[$next]), FLAG_PREFIX)) {
                $next++;

                continue;
            }

            break;
        }

        if ($next >= $count) {
            $findings[$i + 1] = 'flag is the last line of the file';

            continue;
        }

        if (! preg_match(KEY_LINE, $lines[$next])) {
            $findings[$i + 1] = 'no key below it (line '.($next + 1).': '.trim($lines[$next]).')';
        }
    }

    return $findings;
}

$report = [
    'generated_by' => 'scripts/locale-curation-flag-scan.php',
    'locales' => [],
];
$total = 0;

echo str_pad('locale', 8), str_pad('flags', 8), str_pad('files', 8), 'orphaned'.PHP_EOL;

foreach ($locales as $locale) {
    $files = locale_scan_php_files($root, $locale);
    $flags = 0;
    $details = [];

    foreach ($files as $label => $path) {
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $flags += count(array_filter(
            $lines,
            static fn (string $line): bool => str_starts_with(ltrim($line), FLAG_PREFIX),
        ));

        foreach (curationFlagFindings($path) as $line => $reason) {
            $details[] = ['file' => $label, 'line' => $line, 'reason' => $reason];
        }
    }

    $report['locales'][$locale] = [
        'flags' => $flags,
        'files' => count($files),
        'orphaned' => count($details),
        'details' => $details,
    ];
    $total += count($details);

    echo str_pad($locale, 8), str_pad((string) $flags, 8), str_pad((string) count($files), 8), count($details), PHP_EOL;
}

locale_scan_write_json($jsonOut, $report);

echo PHP_EOL, 'total orphaned curation flags: ', $total, PHP_EOL;

if ($verbose) {
    foreach ($report['locales'] as $locale => $entry) {
        foreach ($entry['details'] as $detail) {
            echo PHP_EOL, $locale, '  ', $detail['file'], ':', $detail['line'], PHP_EOL;
            echo '    ', $detail['reason'], PHP_EOL;
        }
    }
}

exit($total === 0 ? 0 : 1);
