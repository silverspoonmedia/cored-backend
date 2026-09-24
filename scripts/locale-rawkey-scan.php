<?php

// Raw-key scan — a translation value that is itself a key.
//
// The failure mode is the worst one in i18n because it is invisible to every other
// guard: the key exists, parity is perfect, the placeholders match, and the user is
// shown `web.settings.fields.timeout` instead of a label. It happens when a
// translator copies the key into the value slot, or when a curation batch writes the
// key on both sides of the `=>`.
//
// This scans values, not keys, on all three surfaces of all supported locales:
//
//   lang/{locale}/**/*.php · lang/{locale}.json · ../monolith-vue-workers/src/i18n/locales/*.json
//
// A value counts as a leaked key when it looks like a dotted key path
// (`web.settings.title`, `api/payments.updated`) *and* resolves as a key somewhere in
// the same locale — resolving is what separates a leaked key from prose that happens
// to contain a dot. Bare domain names and file names are excluded explicitly, since
// `youtube.com` and `Routing_Price.yaml` have the same shape.
//
// Zero tolerance: any hit exits 1.
//
// Usage:
//   php scripts/locale-rawkey-scan.php                all locales
//   php scripts/locale-rawkey-scan.php ko ru          only these locales
//   php scripts/locale-rawkey-scan.php --verbose      per-key detail
//   php scripts/locale-rawkey-scan.php --json=out.json also write the worklist
require __DIR__.'/lib/locale-scan-support.php';

$root = locale_scan_root();
$spaRoot = locale_scan_spa_root();
locale_scan_require_spa($spaRoot);

$locales = locale_scan_supported();

[$requested, $flags] = locale_scan_args($argv);
$verbose = in_array('--verbose', $flags, true);
$jsonOut = locale_scan_flag_value($flags, '--json');
$locales = locale_scan_select($locales, $requested, 'locale');

$hits = [];
$report = [];

foreach ($locales as $locale) {
    $surfaces = locale_scan_surfaces($root, $spaRoot, $locale);

    // Every key the locale defines on any surface. A value that resolves here is a
    // leaked key, not prose that happens to contain a dot.
    $known = locale_scan_backend_keys($root, $locale);

    foreach (array_keys(locale_scan_flatten_file(locale_scan_spa_file($spaRoot, $locale))) as $key) {
        $known[$key] = true;
    }

    $localeHits = 0;

    foreach ($surfaces as $label => $path) {
        if (! is_file($path)) {
            fwrite(STDERR, $locale.'  missing surface: '.$label.PHP_EOL);
            exit(1);
        }

        foreach (locale_scan_flatten_file($path) as $key => $value) {
            if (! locale_scan_looks_like_key($value) || ! locale_scan_resolves($value, $known)) {
                continue;
            }

            $localeHits++;
            $hits[] = [$locale, $label, $key, $value];
            $report[] = [
                'locale' => $locale,
                'file' => $label,
                'key' => $key,
                'value' => $value,
                'resolves_as' => trim($value),
            ];
        }
    }

    echo str_pad($locale, 8), str_pad((string) $localeHits, 8), 'leaked key value(s)', PHP_EOL;
}

locale_scan_write_json($jsonOut, [
    'generated_by' => 'scripts/locale-rawkey-scan.php',
    'hits' => $report,
]);

echo PHP_EOL, 'total leaked key values: ', count($hits), PHP_EOL;

if ($verbose) {
    foreach ($hits as [$locale, $file, $key, $value]) {
        echo PHP_EOL, $locale, '  ', $file, '  ', $key, PHP_EOL;
        echo '    value : ', $value, PHP_EOL;
        echo '    -> renders ', $value, ' instead of copy', PHP_EOL;
    }
}

exit($hits === [] ? 0 : 1);
