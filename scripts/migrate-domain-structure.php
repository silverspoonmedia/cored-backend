<?php

/**
 * Move files into app/Domain/{Domain}/ and tests/Domain/{Domain}/, then apply namespace replacements.
 *
 * Usage: php scripts/migrate-domain-structure.php <DomainName> <config.json>
 *
 * Config JSON shape:
 * {
 *   "replacements": { "App\\Old\\": "App\\Domain\\X\\New\\" },
 *   "moves": [
 *     { "from": "app/Services/Foo/Bar.php", "to": "app/Domain/X/Services/Bar.php" }
 *   ],
 *   "test_moves": [
 *     { "from": "tests/Unit/FooTest.php", "to": "tests/Domain/X/Unit/FooTest.php" }
 *   ],
 *   "extra_imports": {
 *     "app/Domain/X/Models/Menu.php": ["App\\Models\\Role", "App\\Models\\User"]
 *   }
 * }
 */

$domain = $argv[1] ?? null;
$configFile = $argv[2] ?? null;

if ($domain === null || $configFile === null || ! is_file($configFile)) {
    fwrite(STDERR, "Usage: php scripts/migrate-domain-structure.php <Domain> <config.json>\n");
    exit(1);
}

$root = dirname(__DIR__);
$config = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);

foreach ($config['moves'] ?? [] as $move) {
    $from = $root.'/'.$move['from'];
    $to = $root.'/'.$move['to'];

    if (! is_file($from)) {
        fwrite(STDERR, "Skip missing: {$from}\n");
        continue;
    }

    $dir = dirname($to);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    rename($from, $to);
    echo "Moved: {$move['from']} -> {$move['to']}\n";
}

foreach ($config['glob_moves'] ?? [] as $move) {
    $pattern = $root.'/'.str_replace('\\', '/', $move['from']);
    $targetDir = $root.'/'.str_replace('\\', '/', rtrim($move['to'], '/'));

    if (! is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    foreach (glob($pattern) ?: [] as $from) {
        $to = $targetDir.'/'.basename($from);
        rename($from, $to);
        echo "Glob moved: {$from} -> {$to}\n";
    }
}

foreach ($config['test_moves'] ?? [] as $move) {
    $from = $root.'/'.$move['from'];
    $to = $root.'/'.$move['to'];

    if (! is_file($from)) {
        fwrite(STDERR, "Skip missing test: {$from}\n");
        continue;
    }

    $dir = dirname($to);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    rename($from, $to);
    echo "Moved test: {$move['from']} -> {$move['to']}\n";
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$replacements = $config['replacements'] ?? [];

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR)) {
        continue;
    }

    $content = file_get_contents($path);
    $original = $content;

    foreach ($replacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
    }
}

passthru('php '.escapeshellarg($root.'/scripts/fix-domain-namespaces.php').' '.escapeshellarg($domain), $exitCode);

if ($exitCode !== 0) {
    exit($exitCode);
}

foreach ($config['extra_imports'] ?? [] as $relativeFile => $imports) {
    $file = $root.'/'.$relativeFile;
    if (! is_file($file)) {
        continue;
    }

    $content = file_get_contents($file);
    $insert = '';
    foreach ($imports as $import) {
        if (! str_contains($content, 'use '.$import.';')) {
            $insert .= "use {$import};\n";
        }
    }

    if ($insert !== '') {
        $content = preg_replace(
            '/(namespace [^;]+;\R)/',
            '$1'."\n".$insert,
            $content,
            1
        );
        file_put_contents($file, $content);
        echo "Added imports to {$relativeFile}\n";
    }
}

echo "Domain {$domain} migration complete.\n";
