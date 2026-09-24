<?php

// Cross-repo framework-pack parity report.
//
// The SPA mirrors the backend framework packs as `validation.*`,
// `http-statuses.*`, `actions.*`, `auth.*`, `passwords.*`, `pagination.*`. A
// validation error can be rendered from either side depending on where the
// request was rejected, so the two copies must carry the same wording — they may
// differ only in the placeholder sigil (Laravel `:attribute`, vue-i18n
// `{attribute}`) and the vue-i18n literal-`@` escape (`{'@'}`).
//
// Neither repo's own guard can see a divergence between the copies, so this
// script is the manual gate to run after any wave that touches a framework pack.
//
// Usage:
//   php scripts/locale-pack-parity.php                 all locales, summary
//   php scripts/locale-pack-parity.php ko ar           only these locales
//   php scripts/locale-pack-parity.php --verbose       per-key detail
//   php scripts/locale-pack-parity.php --json=out.json also write the full report
//
// Exit code is 0 only when every checked locale is at 0 divergent, so this doubles
// as a gate. The JSON report is opt-in (`--json=…`) rather than always-on: unlike
// `backend-locale-audit.php`, whose report is the reviewed inventory, the useful
// output here is the per-locale divergence count and the exit code.
//
// The sibling repo is required. This guard's whole purpose is the backend↔SPA
// comparison, so a run without `monolith-vue-workers` compares nothing and must fail
// rather than report `0 divergent` — that was the earlier "green by absence" bug.
require __DIR__.'/lib/locale-scan-support.php';

$root = locale_scan_root();
$spaRoot = locale_scan_spa_root();
locale_scan_require_spa($spaRoot);

$packs = ['validation', 'http-statuses', 'actions', 'auth', 'passwords', 'pagination'];

/**
 * `actions.*` is deliberately NOT a mirror. Only `validation.*` and
 * `http-statuses.*` are documented copies of the backend packs; `auth.*`,
 * `passwords.*` and `pagination.*` are small framework messages that render on
 * both sides and are kept in step too. The SPA `actions.*` block is the SPA's own
 * shared action vocabulary, curated against its own surfaces (buttons, row
 * actions, datatable columns) — `ja` uses 操作 where the pack says アクション,
 * `ar` uses الإجراءات where the pack says إجراءات, and `ko` uses noun forms
 * (추가, 취소) where the pack uses verbal nouns (추가하기, 취소하기). Comparing it
 * here would report a difference that is the intended design.
 */
$mirroredPacks = ['validation', 'http-statuses', 'auth', 'passwords', 'pagination'];
$locales = ['ar', 'id', 'ja', 'ko', 'ru', 'zh_CN'];

[$requested, $flags] = locale_scan_args($argv);
$verbose = in_array('--verbose', $flags, true);
$jsonOut = locale_scan_flag_value($flags, '--json');
$locales = locale_scan_select($locales, $requested, 'locale');

/**
 * @return array<string, string>
 */
function flattenPack(array $data, string $prefix = ''): array
{
    $out = [];

    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $out += flattenPack($value, $path);
        } elseif (is_string($value)) {
            $out[$path] = $value;
        }
    }

    return $out;
}

/**
 * Compare wording, not the placeholder sigil or its casing: Laravel substitutes
 * both `:attribute` and `:Attribute`, vue-i18n only ever sees `{attribute}`, and
 * a literal `@` is written `{'@'}` in vue-i18n messages.
 *
 * HTML entities are a surface contract too, not a translation choice: the
 * Laravel `pagination` pack ships `&laquo;`/`&raquo;` because Blade escapes by
 * default, while the SPA renders through vue-i18n and several locales carry the
 * literal characters instead. Same rendered glyph, so compare them as equal.
 */
function normalizePackValue(string $value): string
{
    static $entities = [
        '&laquo;' => '«',
        '&raquo;' => '»',
        '&amp;' => '&',
        '&hellip;' => '…',
        '&nbsp;' => ' ',
    ];

    $value = str_replace("{'@'}", '@', $value);
    $value = str_replace(array_keys($entities), array_values($entities), $value);
    $value = preg_replace_callback(
        '/:([A-Za-z_][A-Za-z0-9_]*)/',
        static fn (array $m): string => '{'.strtolower($m[1]).'}',
        $value,
    );
    $value = preg_replace_callback(
        '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
        static fn (array $m): string => '{'.strtolower($m[1]).'}',
        $value,
    );

    return trim((string) preg_replace('/\s+/', ' ', (string) $value));
}

$report = [
    'generated_by' => 'scripts/locale-pack-parity.php',
    'packs' => $packs,
    'mirrored_packs' => $mirroredPacks,
    'locales' => [],
];

$missingPacks = [];

foreach ($locales as $locale) {
    $spaFile = $spaRoot.'/src/i18n/locales/'.str_replace('zh_CN', 'zh-CN', $locale).'.json';
    $entry = ['shared' => 0, 'divergent' => 0, 'by_pack' => [], 'details' => []];

    if (! is_file($spaFile)) {
        // The repo exists (asserted above) but this locale's pack does not: a missing
        // pack is a finding, not a silent zero.
        fwrite(STDERR, 'missing SPA locale file: '.$spaFile.PHP_EOL);
        $missingPacks[] = $locale;

        $entry['error'] = 'missing SPA locale file: '.$spaFile;
        $report['locales'][$locale] = $entry;

        continue;
    }

    $spa = flattenPack((array) json_decode((string) file_get_contents($spaFile), true));

    foreach ($mirroredPacks as $pack) {
        $file = $root.'/lang/'.$locale.'/'.$pack.'.php';

        if (! is_file($file)) {
            continue;
        }

        $divergent = 0;
        $shared = 0;

        foreach (flattenPack((array) include $file) as $key => $value) {
            $full = $pack.'.'.$key;

            if (! array_key_exists($full, $spa)) {
                continue;
            }

            $shared++;

            if (normalizePackValue($value) === normalizePackValue((string) $spa[$full])) {
                continue;
            }

            $divergent++;
            $entry['details'][] = ['key' => $full, 'backend' => $value, 'spa' => $spa[$full]];
        }

        $entry['by_pack'][$pack] = ['shared' => $shared, 'divergent' => $divergent];
        $entry['shared'] += $shared;
        $entry['divergent'] += $divergent;
    }

    $report['locales'][$locale] = $entry;
}

if ($jsonOut !== null) {
    file_put_contents($jsonOut, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
}

echo str_pad('locale', 8), str_pad('shared', 8), str_pad('divergent', 11), 'per pack'.PHP_EOL;

foreach ($report['locales'] as $locale => $entry) {
    if (isset($entry['error'])) {
        echo str_pad($locale, 8), $entry['error'].PHP_EOL;

        continue;
    }

    $perPack = [];

    foreach ($entry['by_pack'] as $pack => $counts) {
        if ($counts['divergent'] > 0) {
            $perPack[] = $pack.'='.$counts['divergent'];
        }
    }

    echo str_pad($locale, 8), str_pad((string) $entry['shared'], 8), str_pad((string) $entry['divergent'], 11), implode(' ', $perPack), PHP_EOL;
}

$total = array_sum(array_map(fn (array $e): int => $e['divergent'] ?? 0, $report['locales']));

if ($verbose) {
    foreach ($report['locales'] as $locale => $entry) {
        foreach ($entry['details'] ?? [] as $detail) {
            echo PHP_EOL, $locale, '  ', $detail['key'], PHP_EOL;
            echo '    backend: ', $detail['backend'], PHP_EOL;
            echo '    spa    : ', $detail['spa'], PHP_EOL;
        }
    }
}

echo PHP_EOL, 'total divergent: ', $total, PHP_EOL;

if ($missingPacks !== []) {
    fwrite(STDERR, 'missing SPA packs for: '.implode(', ', $missingPacks).PHP_EOL);
    exit(1);
}

exit($total === 0 ? 0 : 1);
