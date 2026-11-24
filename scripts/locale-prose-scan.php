<?php

// Locale prose scan — English left inside translated prose.
//
// Every structural guard is blind to this class. `LocaleParityTest` compares key
// sets, `check-i18n.mjs` compares a value against `en` and skips it the moment it
// differs, and the duplicate-English policy only fires on an exact copy of the
// English value. A value that says `Nama management wajib diisi.` differs from
// English, is not a duplicate, and contains no forbidden glossary variant — so
// nothing reported it while 26 backend keys and 20 SPA keys shipped that way.
//
// Two modes, because the locales fail differently:
//
//   - `script` (`ja`, `ko`, `zh_CN`, `ar`): any Latin word of 3+ letters inside a
//     value that also contains target-script text is a hit, unless it is a machine
//     token or an accepted loanword.
//   - `english` (`id`): a Latin scan is pure noise, so only the English domain
//     terms whose settled `id` rendering differs from the English word are
//     reported — `management` (→ `manajemen`), `role` (→ `peran`), `creator`
//     (→ `kreator`). Terms the glossary keeps in English (`workspace`, `channel`,
//     `checkout`, `invoice`) are accepted, not reported.
//
// Both modes strip the same non-copy content first: placeholders, snake_case and
// camelCase identifiers, quoted enum literals, `key=value` pairs, URLs, and CLI
// flags. Without that the scan reports `reference_id`, `cascade=true`, `setPrimary`
// and `oauth_app` as English prose, which is what made the earlier throwaway
// version unusable as a gate.
//
// Usage:
//   php scripts/locale-prose-scan.php                all locales with a policy
//   php scripts/locale-prose-scan.php ja id          only these locales
//   php scripts/locale-prose-scan.php --verbose      list every hit
//   php scripts/locale-prose-scan.php --json=out.json also write the worklist
//
// Exit code is 1 when any hit is found, so this doubles as a gate.
require __DIR__.'/lib/locale-scan-support.php';

$root = locale_scan_root();
$spaRoot = locale_scan_spa_root();
locale_scan_require_spa($spaRoot);

/** @var array{machine_patterns: array<int, string>, machine: array<int, string>, glossary: array<string, array<string, string>>, accepted: array<string, array<string, string>>} $policy */
$policy = require __DIR__.'/lib/locale-latin-allowlist.php';

// Only locales the application still supports carry a prose policy that matters;
// a retired one stays in the policy map (it is the wording reference if the
// language is ever added back) but is skipped by the scan.
$locales = array_values(array_intersect(['ar', 'id', 'ja', 'ko', 'ru', 'zh_CN'], locale_scan_supported()));

[$requested, $flags] = locale_scan_args($argv);
$verbose = in_array('--verbose', $flags, true);
$jsonOut = locale_scan_flag_value($flags, '--json');
$locales = locale_scan_select($locales, $requested, 'locale with a prose policy');

/**
 * Machine tokens may be single words or phrases. A phrase is removed from the value
 * before token extraction, so `Team Workspace Slot` does not leave `Team`,
 * `Workspace`, and `Slot` behind as separate hits.
 *
 * @param  array<int, string>  $tokens
 * @return array{0: array<int, string>, 1: array<int, string>}
 */
function splitTokens(array $tokens): array
{
    $phrases = [];
    $words = [];

    foreach ($tokens as $token) {
        if (str_contains($token, ' ')) {
            $phrases[] = $token;
        } else {
            $words[] = strtolower($token);
        }
    }

    return [$phrases, $words];
}

[$machinePhrases, $machineWords] = splitTokens($policy['machine']);

/**
 * Strip everything that is not prose copy: placeholders, identifiers, literals,
 * commands, URLs, and flags. What remains is the text a user actually reads.
 *
 * @param  array<int, string>  $patterns
 */
function proseOnly(string $value, array $patterns): string
{
    $value = str_replace("{'@'}", '', $value);

    foreach ($patterns as $pattern) {
        $value = preg_replace($pattern, '', $value) ?? $value;
    }

    return $value;
}

/**
 * Latin tokens in prose. A trailing digit run is part of the token, so `IPv4` and
 * `sha256` come out whole instead of as `IPv` and `sha`.
 *
 * @return array<int, string>
 */
function latinWords(string $value): array
{
    preg_match_all('/[A-Za-z]{3,}[0-9]*/', $value, $matches);

    return array_values(array_unique($matches[0]));
}

/**
 * Flatten a PHP lang file or a JSON pack into dotted key => value.
 *
 * @return array<string, string>
 */
function flatten(string $file): array
{
    return locale_scan_flatten_file($file);
}

$scriptPatterns = [
    'ja' => '/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}]/u',
    'ko' => '/[\x{AC00}-\x{D7AF}]/u',
    'zh_CN' => '/[\x{4E00}-\x{9FFF}]/u',
    'ar' => '/[\x{0600}-\x{06FF}]/u',
    'ru' => '/[\x{0400}-\x{04FF}]/u',
];

$hits = [];
$report = [];

foreach ($locales as $locale) {
    $mode = $locale === 'id' ? 'english' : 'script';
    $accepted = array_map('strtolower', array_keys($policy['accepted'][$locale] ?? []));

    // For `english` mode the watched set is the glossary terms whose settled `id`
    // rendering is a different word. A term the glossary keeps in English is not a
    // defect and is not watched.
    $watched = [];
    $watchedLower = [];
    if ($mode === 'english') {
        foreach ($policy['glossary'] as $english => $renderings) {
            $settled = $renderings[$locale] ?? null;

            if ($settled === null) {
                continue;
            }

            foreach (explode('/', $settled) as $variant) {
                if (strtolower(trim($variant)) === $english) {
                    continue 2;
                }
            }

            $watched[] = $english;
        }
        $watched = array_values(array_diff($watched, $accepted));
        $watchedLower = array_map('strtolower', $watched);
    }

    $files = locale_scan_surfaces($root, $spaRoot, $locale);

    if (! is_file(locale_scan_spa_file($spaRoot, $locale))) {
        fwrite(STDERR, 'missing SPA pack: '.locale_scan_spa_file($spaRoot, $locale).PHP_EOL);
        exit(1);
    }

    foreach ($files as $label => $path) {
        foreach (flatten($path) as $key => $value) {
            $prose = proseOnly($value, $policy['machine_patterns']);

            if ($mode === 'script') {
                $pattern = $scriptPatterns[$locale] ?? null;

                if ($pattern === null || ! preg_match($pattern, $value)) {
                    continue;
                }

                foreach ($machinePhrases as $phrase) {
                    $prose = str_ireplace($phrase, '', $prose);
                }

                $bad = array_values(array_filter(
                    latinWords($prose),
                    static fn (string $word): bool => ! in_array(strtolower($word), $machineWords, true)
                        && ! in_array(strtolower($word), $accepted, true),
                ));
            } else {
                $bad = array_values(array_filter(
                    latinWords($prose),
                    static fn (string $word): bool => in_array(strtolower($word), $watchedLower, true),
                ));
            }

            if ($bad === []) {
                continue;
            }

            $hits[] = [$locale, $label, $key, $value, implode(', ', array_unique($bad))];
            $report[] = [
                'locale' => $locale,
                'mode' => $mode,
                'file' => $label,
                'key' => $key,
                'words' => array_values(array_unique($bad)),
                'value' => $value,
            ];
        }
    }

    $localeHits = array_filter($hits, static fn (array $h): bool => $h[0] === $locale);
    echo str_pad($locale, 8), str_pad($mode, 9), str_pad((string) count($localeHits), 8), 'hit(s)', PHP_EOL;
}

if ($jsonOut !== null) {
    file_put_contents($jsonOut, json_encode([
        'generated_by' => 'scripts/locale-prose-scan.php',
        'hits' => $report,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
}

echo PHP_EOL, 'total hits: ', count($hits), PHP_EOL;

if ($verbose) {
    foreach ($hits as [$locale, $file, $key, $value, $words]) {
        echo PHP_EOL, $locale, '  ', $file, '  ', $key, PHP_EOL;
        echo '    words: ', $words, PHP_EOL;
        echo '    ', $value, PHP_EOL;
    }
}

exit($hits === [] ? 0 : 1);
