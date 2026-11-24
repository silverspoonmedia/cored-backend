<?php

// Flat-pack drift guard: `lang/{locale}.json` vs the PHP namespaces.
//
// Laravel's `Translator::get()` consults the JSON pack **before** the PHP
// namespaces for any key without a `/`. 177 of the 228 scalars in
// `lang/{locale}.json` are the *same English string* that also exists as a value
// in `lang/en/**/*.php`, which means the same English phrase has two independent
// renderings — one from the pack, one from the namespace. Nothing compared them,
// which is how `ko` kept 16 drifted scalars through its whole wave and `ru` kept
// 55, including 37 HTTP status phrases that had already been settled once in
// `http-statuses.php`.
//
// The remaining 51 scalars are package chrome with no PHP owner. They are curated
// against the target language on their own and are reported as `unowned` only.
//
// Ownership is resolved to a single **canonical** owner per English phrase, so the
// 26 phrases that appear in more than one PHP file do not report a false positive
// for every owner they are not. Preference order:
//
//   http-statuses -> actions -> ui -> first `web/*` -> first `api/*` -> alphabetical
//
// A status phrase is unambiguous; `actions.php` is the generic action vocabulary
// closest to the pack's button/label surface. Where two namespaces disagree for
// the *same concept* (e.g. `id` `Cancel`: `actions.php` `Membatalkan` vs `ui.php`
// `Batal`) the owning namespace is fixed in the locale's wave — the guard only
// reports the divergence it is handed.
//
// Usage:
//   php scripts/locale-flat-pack-parity.php                     all locales, summary
//   php scripts/locale-flat-pack-parity.php ko ru               only these locales
//   php scripts/locale-flat-pack-parity.php --verbose           per-key detail
//   php scripts/locale-flat-pack-parity.php --dump=out.txt      write the review sheet
//   php scripts/locale-flat-pack-parity.php --json=out.json     also write the report
//
// Exit code is 0 only when every checked locale is at 0 divergent, so this doubles
// as a gate. The review sheet (`--dump`) is the input to a curation batch: it
// prints `EN \t current \t canonical owner \t owner value` per scalar.
require __DIR__.'/lib/locale-scan-support.php';

$root = locale_scan_root();

$locales = ['ar', 'id', 'ja', 'ko', 'ru', 'zh_CN'];

// Preference order for the canonical owner of an English phrase that exists in
// more than one PHP file. Lower sorts first.
//
// `ui` outranks `actions` because the flat pack is package chrome — buttons,
// datatable headers, dialog labels — which is exactly what `ui.php` holds.
// `actions.php` is the *named-action* pack: its entries are built to take a
// `:name` (`Add :name`, `Add :name` → `追加 :name`), and it deliberately uses a
// different register (`ko` `추가하기`, `id` `Menambahkan`, `ar` `إجراءات`), which is
// correct there and wrong in a bare button label.
const OWNER_PRIORITY = ['http-statuses', 'ui', 'actions'];

/**
 * Phrases whose concept differs between namespaces, so the priority order would
 * pick the wrong copy. Each entry is the decision, not a workaround: the flat-pack
 * scalar is the key on the left, and the owner is the namespace that renders that
 * concept.
 */
const OWNER_OVERRIDES = [
    // `OK` is a dialog button in the pack (`ui.swal.ok`), not the HTTP 200 phrase.
    'OK' => ['ui', 'swal.ok'],
    // The pack `File`/`Files` are noun labels; `actions.php` renders the verb
    // "to file a document" (`id` `Mengajukan`) and is the wrong copy. The product
    // namespace that first exposed the split left with its domain, so the owner is
    // the generic `ui` namespace — the concept, not the file that used to hold it.
    'File' => ['ui', 'type_file'],
];

/**
 * English scalars with no backend namespace left to own them.
 *
 * `Connect` was overridden to the button on a screen that is now SPA-only
 * (`src/i18n/locales/*.json`), and its backend pack
 * (`lang/{locale}/web/identity-connections.php`) was deleted with its last
 * backend reader. Falling back to `actions.php` would compare the pack against
 * the *named-action* register (`ko` `연결하기`, `ar` `اتصال`), which is correct
 * there and wrong in a bare button label, so the guard would report three
 * phantom drifts. There is no honest backend owner: report the scalar as
 * unowned, not as divergent.
 *
 * @var list<string>
 */
const UNOWNED_SCALARS = ['Connect'];

[$requested, $flags] = locale_scan_args($argv);
$verbose = in_array('--verbose', $flags, true);
$jsonOut = locale_scan_flag_value($flags, '--json');
$dumpOut = locale_scan_flag_value($flags, '--dump');
$locales = locale_scan_select($locales, $requested, 'locale');

// The English namespaces are the owner side of every comparison. Without them every
// scalar is `unowned`, `divergent` stays 0, and the guard would report success while
// comparing nothing — so their absence is a hard failure, not a silent zero.
if (! is_dir($root.'/lang/en') || locale_scan_php_files($root, 'en') === []) {
    fwrite(STDERR, 'cannot read the English namespaces at '.$root.'/lang/en'.PHP_EOL);
    fwrite(STDERR, 'They are the owner side of every flat-pack comparison, so a run without them proves nothing.'.PHP_EOL);
    exit(1);
}

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
 * Every `lang/{locale}/**` .php file as `relative/name` => flattened values.
 *
 * Reads the host tree plus every packaged domain's lang tree, so a packaged
 * domain's keys keep taking part in the flat-pack ownership comparison.
 *
 * @return array<string, array<string, string>>
 */
function namespaceValues(string $root, string $locale): array
{
    $files = [];

    foreach (locale_scan_lang_roots() as $langRoot) {
        $base = $langRoot.'/'.$locale;

        if (! is_dir($base)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            $relative = substr($relative, 0, -4);

            if (array_key_exists($relative, $files)) {
                continue;
            }

            $data = include $file->getPathname();

            if (is_array($data)) {
                $files[$relative] = flattenPack($data);
            }
        }
    }

    ksort($files);

    return $files;
}

/**
 * Rank a namespace path for the canonical-owner tie-break.
 */
function ownerRank(string $namespace): int
{
    $head = explode('/', $namespace)[0];
    $index = array_search($head, OWNER_PRIORITY, true);

    if ($index !== false) {
        return $index;
    }

    if (str_starts_with($namespace, 'web/')) {
        return 10;
    }

    if (str_starts_with($namespace, 'api/')) {
        return 20;
    }

    return 30;
}

// English phrase => every namespace+key that carries it, best-ranked first.
$owners = [];

foreach (namespaceValues($root, 'en') as $namespace => $values) {
    foreach ($values as $key => $value) {
        if ($value === '') {
            continue;
        }

        $owners[$value][] = [$namespace, $key];
    }
}

foreach ($owners as $value => $specs) {
    usort($specs, static function (array $a, array $b): int {
        return ownerRank($a[0]) <=> ownerRank($b[0]) ?: strcmp($a[0], $b[0]);
    });

    if (isset(OWNER_OVERRIDES[$value])) {
        $picked = OWNER_OVERRIDES[$value];
        $rest = array_values(array_filter(
            $specs,
            static fn (array $spec): bool => $spec !== $picked,
        ));
        $specs = [$picked, ...$rest];
    }

    $owners[$value] = $specs;
}

$report = [
    'generated_by' => 'scripts/locale-flat-pack-parity.php',
    'locales' => [],
];
$dump = [];
$total = 0;

foreach ($locales as $locale) {
    $packFile = $root.'/lang/'.$locale.'.json';

    if (! is_file($packFile)) {
        $report['locales'][$locale] = ['error' => 'missing flat pack'];
        $total++;

        continue;
    }

    $pack = flattenPack((array) json_decode((string) file_get_contents($packFile), true));
    $namespaces = namespaceValues($root, $locale);
    $entry = ['scalars' => count($pack), 'owned' => 0, 'unowned' => 0, 'divergent' => 0, 'details' => []];

    // A locale with a flat pack but no namespace directory cannot be compared at all:
    // every scalar would come out `unowned` and the locale would report 0 divergent.
    if ($namespaces === []) {
        fwrite(STDERR, 'cannot read the '.$locale.' namespaces at '.$root.'/lang/'.$locale.PHP_EOL);
        $entry['error'] = 'missing namespace directory: lang/'.$locale;
        $report['locales'][$locale] = $entry;
        $total++;

        continue;
    }

    foreach ($pack as $english => $current) {
        if (! isset($owners[$english]) || in_array($english, UNOWNED_SCALARS, true)) {
            $entry['unowned']++;

            continue;
        }

        [$namespace, $key] = $owners[$english][0];

        if (! isset($namespaces[$namespace][$key])) {
            $entry['unowned']++;

            continue;
        }

        $entry['owned']++;
        $ownerValue = $namespaces[$namespace][$key];
        $row = [$english, $current, $namespace.'#'.$key, $ownerValue];
        $dump[] = implode("\t", $row);

        if ($current === $ownerValue) {
            continue;
        }

        $entry['divergent']++;
        $entry['details'][] = [
            'english' => $english,
            'pack' => $current,
            'owner' => $namespace.'#'.$key,
            'owner_value' => $ownerValue,
            'other_owners' => array_map(
                static fn (array $s): string => $s[0].'#'.$s[1],
                array_slice($owners[$english], 1),
            ),
        ];
    }

    $report['locales'][$locale] = $entry;
    $total += $entry['divergent'];
}

if ($dumpOut !== null) {
    file_put_contents($dumpOut, implode(PHP_EOL, $dump).PHP_EOL);
}

if ($jsonOut !== null) {
    file_put_contents($jsonOut, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
}

echo str_pad('locale', 8), str_pad('scalars', 9), str_pad('owned', 7), str_pad('unowned', 9), 'divergent'.PHP_EOL;

foreach ($report['locales'] as $locale => $entry) {
    if (isset($entry['error'])) {
        echo str_pad($locale, 8), $entry['error'].PHP_EOL;

        continue;
    }

    echo str_pad($locale, 8), str_pad((string) $entry['scalars'], 9), str_pad((string) $entry['owned'], 7),
    str_pad((string) $entry['unowned'], 9), (string) $entry['divergent'], PHP_EOL;
}

if ($verbose) {
    foreach ($report['locales'] as $locale => $entry) {
        foreach ($entry['details'] ?? [] as $detail) {
            echo PHP_EOL, $locale, '  ', $detail['english'], PHP_EOL;
            echo '    pack   : ', $detail['pack'], PHP_EOL;
            echo '    owner  : ', $detail['owner'], ' = ', $detail['owner_value'], PHP_EOL;

            if ($detail['other_owners'] !== []) {
                echo '    also in: ', implode(', ', $detail['other_owners']), PHP_EOL;
            }
        }
    }
}

echo PHP_EOL, 'total divergent: ', $total, PHP_EOL;

exit($total === 0 ? 0 : 1);
