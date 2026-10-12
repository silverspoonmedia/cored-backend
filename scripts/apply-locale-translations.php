<?php

// Usage:
//   php scripts/apply-locale-translations.php <locale> <file> <map.json> [options]
//
// Applies a contextual translation batch to one backend PHP lang file without
// reformatting the rest of the file (line-surgical: only the touched keys change).
//
//   <locale>     en | id | ja | ko | zh_CN | ar | ru
//   <file>       path relative to lang/<locale>/, e.g. api/archivd.php
//   <map.json>   { "dotted.key": "translated value", ... }
//                Keys that already exist are replaced in place; unknown keys are
//                created (behind --anchor=<key> when given, else at the end of the
//                parent array).
//
// Options:
//   --anchor=<key>      sibling key the created keys are inserted behind
//   --curated-at=<iso>  override the curation timestamp (default: now, +07:00)
//   --note=<text>       extra note appended to the flag comment
//   --dry-run           report what would change, write nothing
//
// Every touched key is flagged with a `// curated at <ISO8601>` comment on the
// line above it, so a translation wave is auditable per key in the same runtime
// file the string lives in. Re-running replaces the existing flag for that key
// instead of stacking duplicates.
$root = dirname(__DIR__);
$locales = ['en', 'id', 'ja', 'ko', 'zh_CN', 'ar', 'ru'];

// The flag marker, matched exactly as `scripts/locale-curation-flag-scan.php` does.
const FLAG_PREFIX = '// curated at ';

$args = array_slice($argv, 1);
$positional = array_values(array_filter($args, fn (string $a): bool => ! str_starts_with($a, '--')));

if (count($positional) < 3) {
    fwrite(STDERR, 'usage: php scripts/apply-locale-translations.php <locale> <file> <map.json> [--anchor=<key>] [--curated-at=<iso>] [--note=<text>] [--dry-run]'.PHP_EOL);
    exit(1);
}

[$locale, $file, $mapPath] = $positional;

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
$anchor = $flag('anchor');
$note = $flag('note');
$curatedAt = $flag('curated-at') ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('c');

$target = $root.'/lang/'.$locale.'/'.ltrim($file, '/');

if (! is_file($target)) {
    fwrite(STDERR, 'missing lang file: '.$target.PHP_EOL);
    exit(1);
}

$map = json_decode((string) file_get_contents($mapPath), true);

if (! is_array($map)) {
    fwrite(STDERR, 'map must be a JSON object of dotted key => string value'.PHP_EOL);
    exit(1);
}

$flagLine = '// curated at '.$curatedAt.($note !== null ? ' — '.$note : '');

/**
 * Resolve a dotted path against the real array, where keys may themselves
 * contain dots (`web/settings.php` stores `'platform.jwt.ttl'` as one key).
 * Greedy longest-prefix match at every level, mirroring the SPA applier.
 *
 * @return array<int, string>|null
 */
function resolveSegments(array $data, string $path): ?array
{
    $parts = explode('.', $path);
    $segments = [];
    $node = $data;
    $i = 0;

    while ($i < count($parts)) {
        $matched = false;

        for ($len = count($parts) - $i; $len >= 1; $len--) {
            $candidate = implode('.', array_slice($parts, $i, $len));

            if (! is_array($node) || ! array_key_exists($candidate, $node)) {
                continue;
            }

            $segments[] = $candidate;

            if ($i + $len === count($parts)) {
                return $segments;
            }

            if (is_array($node[$candidate])) {
                $node = $node[$candidate];
                $i += $len;
                $matched = true;

                break;
            }
        }

        if (! $matched) {
            return null;
        }
    }

    return null;
}

/**
 * Line index of a key path, discovered by tracking array nesting through
 * indentation. One entry per line is the convention in every lang file here.
 */
function findKeyLine(array $lines, array $segments): ?int
{
    $stack = [];

    foreach ($lines as $index => $line) {
        if (! preg_match("/^(\s*)'((?:[^'\\\\]|\\\\.)+)'\s*=>/", $line, $m)) {
            continue;
        }

        // Indentation level 0 is the first nesting step inside `return [`.
        $depth = intdiv(strlen($m[1]), 4) - 1;
        $stack = array_slice($stack, 0, max($depth, 0));
        $stack[$depth] = stripcslashes($m[2]);

        if (array_values($stack) === $segments) {
            return $index;
        }
    }

    return null;
}

$lines = file($target, FILE_IGNORE_NEW_LINES);
$data = include $target;

if (! is_array($data)) {
    fwrite(STDERR, 'lang file must return an array'.PHP_EOL);
    exit(1);
}

$replaced = [];
$created = [];
$failed = [];
$anchorLine = null;
$anchorDepth = null;

if ($anchor !== null) {
    $anchorSegments = resolveSegments($data, $anchor);

    if ($anchorSegments === null) {
        fwrite(STDERR, 'anchor key not found: '.$anchor.PHP_EOL);
        exit(1);
    }

    $anchorLine = findKeyLine($lines, $anchorSegments);

    if ($anchorLine === null) {
        fwrite(STDERR, 'anchor key not found in source lines: '.$anchor.PHP_EOL);
        exit(1);
    }

    $anchorDepth = intdiv(strlen($lines[$anchorLine]) - strlen(ltrim($lines[$anchorLine])), 4);

    if (str_ends_with(rtrim($lines[$anchorLine]), '[')) {
        fwrite(STDERR, 'anchor must be a leaf entry, not a container array: '.$anchor.PHP_EOL);
        exit(1);
    }
}

$insertBuffer = [];

foreach ($map as $key => $value) {
    if (! is_string($value)) {
        $failed[] = $key.' (non-string value)';

        continue;
    }

    $segments = resolveSegments($data, (string) $key);
    $line = $segments !== null ? findKeyLine($lines, $segments) : null;

    if (in_array('--debug', $args, true)) {
        fwrite(STDERR, 'debug: key='.$key.' segments='.json_encode($segments).' line='.json_encode($line).PHP_EOL);
    }

    if ($line !== null) {
        $lines[$line] = preg_replace_callback(
            "/^(\s*'(?:[^'\\\\]|\\\\.)+'\s*=>\s*)(.*?)(,?)$/",
            function (array $m) use ($value): string {
                return $m[1]."'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'".$m[3];
            },
            $lines[$line],
        );

        $indent = str_repeat(' ', intdiv(strlen($lines[$line]) - strlen(ltrim($lines[$line])), 4) * 4);

        // Collapse the whole stack of flags above the key into the one being written.
        // Replacing only the line directly above the key left the flags of keys that had
        // since been deleted stacked above the surviving one — 25 of them outlived the
        // 2026-10-07 product-domain sunset that way, and every locale gate stayed green
        // until `locale-curation-flag-scan.php` learned to report a stacked flag.
        $stackTop = $line;

        while ($stackTop > 0 && str_starts_with(ltrim($lines[$stackTop - 1]), FLAG_PREFIX)) {
            $stackTop--;
        }

        array_splice($lines, $stackTop, $line - $stackTop, [$indent.$flagLine]);

        $replaced[] = (string) $key;

        continue;
    }

    if ($anchorLine === null) {
        $failed[] = $key.' (not found; pass --anchor=<key> to create it)';

        continue;
    }

    // A created key is a SIBLING of the anchor, so it keeps the anchor's depth.
    // Dotted names are only accepted with --literal-key, because `status.active`
    // most likely means a nested array this patcher cannot build.
    if (str_contains((string) $key, '.') && ! in_array('--literal-key', $args, true)) {
        $failed[] = $key.' (dotted key cannot be created; use --literal-key for a literal dotted key, or add the parent array first)';

        continue;
    }

    $leaf = (string) $key;
    $indent = str_repeat(' ', $anchorDepth * 4);
    $insertBuffer[] = $indent.$flagLine;
    $insertBuffer[] = $indent."'".str_replace(['\\', "'"], ['\\\\', "\\'"], $leaf)."' => '"
        .str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."',";
    $created[] = (string) $key;
}

if ($insertBuffer !== []) {
    if (! str_ends_with(rtrim($lines[$anchorLine]), ',')) {
        $lines[$anchorLine] = rtrim($lines[$anchorLine]).',';
    }

    array_splice($lines, $anchorLine + 1, 0, $insertBuffer);
}

$output = implode("\n", $lines)."\n";

// Syntax guard: never write a lang file PHP cannot parse.
$probe = tempnam(sys_get_temp_dir(), 'lang-probe-');
file_put_contents($probe, $output);
$check = shell_exec('php -l '.escapeshellarg($probe).' 2>&1');
unlink($probe);

if ($check === null || ! str_contains($check, 'No syntax errors')) {
    fwrite(STDERR, 'refusing to write — generated file does not parse:'.PHP_EOL.$check.PHP_EOL);
    exit(1);
}

if (! $dryRun) {
    file_put_contents($target, $output);
}

echo ($dryRun ? '[dry-run] ' : ''), 'replaced=', count($replaced), ' created=', count($created), ' failed=', count($failed), PHP_EOL;

if ($created !== []) {
    echo 'created: ', implode(', ', $created), PHP_EOL;
}

if ($failed !== []) {
    echo 'failed: ', implode(', ', $failed), PHP_EOL;
    exit(1);
}
