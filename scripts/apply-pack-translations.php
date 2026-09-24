<?php

// Usage:
//   php scripts/apply-pack-translations.php <locale> <map.json> [options]
//
// Applies a contextual translation batch to the flat package pack at
// `lang/<locale>.json` (the laravel-lang JSON pack that Laravel consults
// *before* the PHP namespaces for any key without a `/`).
//
//   <locale>     en | id | ja | ko | zh_CN | ar | ru
//   <map.json>   { "English source string": "translated value", ... }
//                Unknown keys are refused — the pack is package-owned, so this
//                tool only rewrites values that already exist. Adding a key is a
//                `lang:update` concern, not a curation concern.
//
// Options:
//   --curated-at=<iso>  override the curation timestamp (default: now, +07:00)
//   --note=<text>       extra note appended to the curation-log entry
//   --dry-run           report what would change, write nothing
//
// JSON cannot carry comments, so the per-key `curated at <timestamp>` flag that
// PHP lang files hold inline lives in `backend-curation-log.json` (the backend
// twin of the SPA's `src/i18n/curation-log.json`). Each applied key is recorded
// with its timestamp, surface, and source.
//
// The rewrite is line-surgical: every entry in these packs is a single line, so
// only the matched lines change and the surrounding formatting (order, indent,
// EOL, trailing newline) is preserved.
$root = dirname(__DIR__);
$locales = ['en', 'id', 'ja', 'ko', 'zh_CN', 'ar', 'ru'];

$args = array_slice($argv, 1);
$positional = array_values(array_filter($args, fn (string $a): bool => ! str_starts_with($a, '--')));

if (count($positional) < 2) {
    fwrite(STDERR, 'usage: php scripts/apply-pack-translations.php <locale> <map.json> [--curated-at=<iso>] [--note=<text>] [--dry-run]'.PHP_EOL);
    exit(1);
}

[$locale, $mapPath] = $positional;

if (! in_array($locale, $locales, true)) {
    fwrite(STDERR, 'unsupported locale: '.$locale.PHP_EOL);
    exit(1);
}

$flag = function (string $name) use ($args): ?string {
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--'.$name.'=')) {
            return substr($arg, strlen($name) + 3);
        }
    }

    return null;
};

$dryRun = in_array('--dry-run', $args, true);
$note = $flag('note');
$curatedAt = $flag('curated-at') ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('c');

$target = $root.'/lang/'.$locale.'.json';

if (! is_file($target)) {
    fwrite(STDERR, 'missing pack file: '.$target.PHP_EOL);
    exit(1);
}

$map = json_decode((string) file_get_contents($mapPath), true);

if (! is_array($map)) {
    fwrite(STDERR, 'map must be a JSON object of English source => translated value'.PHP_EOL);
    exit(1);
}

$raw = (string) file_get_contents($target);
$eol = str_contains($raw, "\r\n") ? "\r\n" : "\n";
$endsWithNewline = str_ends_with($raw, "\n");
$lines = explode($eol, rtrim($raw, "\r\n"));

$encode = static fn (string $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/** Index every top-level entry by its decoded key, so lookups are exact. */
$lineOf = [];
$keyLiteralOf = [];

foreach ($lines as $index => $line) {
    if (! preg_match('/^(\s*)("(?:[^"\\\\]|\\\\.)*")\s*:\s*(.*?)(,?)\s*$/', $line, $m)) {
        continue;
    }

    $decoded = json_decode($m[2], true);

    if (is_string($decoded)) {
        $lineOf[$decoded] = $index;
        $keyLiteralOf[$decoded] = $m[1].$m[2];
    }
}

$replaced = [];
$unknown = [];
$logRecords = [];

foreach ($map as $key => $value) {
    $key = (string) $key;

    if (! is_string($value)) {
        $unknown[] = $key.' (non-string value)';

        continue;
    }

    if (! array_key_exists($key, $lineOf)) {
        $unknown[] = $key.' (not in '.$locale.'.json)';

        continue;
    }

    $index = $lineOf[$key];
    $isLast = $index === array_key_last($lineOf) || ! str_ends_with(rtrim($lines[$index]), ',');

    $lines[$index] = $keyLiteralOf[$key].': '.$encode($value).($isLast ? '' : ',');

    $replaced[] = $key;
    $logRecords[$key] = $value;
}

if ($unknown !== []) {
    fwrite(STDERR, 'refusing to write — unknown keys:'.PHP_EOL.'  '.implode(PHP_EOL.'  ', $unknown).PHP_EOL);
    exit(1);
}

$output = implode($eol, $lines).($endsWithNewline ? $eol : '');

if (json_decode($output, true) === null) {
    fwrite(STDERR, 'refusing to write — generated pack is not valid JSON'.PHP_EOL);
    exit(1);
}

if (! $dryRun) {
    file_put_contents($target, $output);

    if ($logRecords !== []) {
        $logPath = $root.'/backend-curation-log.json';
        $log = is_file($logPath) ? json_decode((string) file_get_contents($logPath), true) : null;

        if (! is_array($log)) {
            $log = ['version' => 1, 'locales' => []];
        }

        $log['version'] = 1;
        $log['updated_at'] = $curatedAt;
        $log['locales'] ??= [];
        $log['locales'][$locale] ??= [];

        foreach ($logRecords as $key => $value) {
            $log['locales'][$locale][$key] = [
                'curated_at' => $curatedAt,
                'value' => $value,
                'surface' => 'flat package pack (laravel-lang JSON)',
                'source' => 'lang/'.$locale.'.json',
            ];
        }

        if ($note !== null) {
            $log['notes'] ??= [];
            $log['notes'][$locale] ??= [];

            if (! in_array($note, $log['notes'][$locale], true)) {
                $log['notes'][$locale][] = $note;
            }

            ksort($log['notes']);
        }

        $sorted = [];

        foreach (array_keys($log['locales']) as $loc) {
            ksort($log['locales'][$loc]);
            $sorted[$loc] = $log['locales'][$loc];
        }

        ksort($sorted);
        $log['locales'] = $sorted;

        file_put_contents($logPath, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
}

echo ($dryRun ? '[dry-run] ' : ''), 'replaced=', count($replaced), PHP_EOL;

if ($replaced !== []) {
    echo 'replaced: ', implode(', ', $replaced), PHP_EOL;
}
