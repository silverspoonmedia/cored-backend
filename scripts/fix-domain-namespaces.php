<?php

/**
 * Fix namespace declarations in moved domain files based on PSR-4 path.
 * Usage: php scripts/fix-domain-namespaces.php Navigation
 */

$domain = $argv[1] ?? null;

if ($domain === null) {
    fwrite(STDERR, "Usage: php scripts/fix-domain-namespaces.php <DomainName>\n");
    exit(1);
}

$root = dirname(__DIR__);

$maps = [
    [
        'base' => "{$root}/app/Domain/{$domain}",
        'prefix' => "App\\Domain\\{$domain}",
    ],
    [
        'base' => "{$root}/tests/Domain/{$domain}",
        'prefix' => "Tests\\Domain\\{$domain}",
    ],
];

foreach ($maps as $map) {
    if (! is_dir($map['base'])) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($map['base'], FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($map['base']) + 1));
        $relative = preg_replace('/\.php$/', '', $relative);
        $nsSuffix = str_replace('/', '\\', $relative);
        $expected = $nsSuffix === basename($relative) && ! str_contains($relative, '/')
            ? $map['prefix']
            : $map['prefix'].'\\'.$nsSuffix;

        // For files directly in subdirs, the namespace is prefix + subpath without filename
        $dir = dirname(str_replace('\\', '/', $relative));
        if ($dir === '.') {
            $expected = $map['prefix'];
        } else {
            $expected = $map['prefix'].'\\'.str_replace('/', '\\', $dir);
        }

        $content = file_get_contents($file->getPathname());
        $updated = preg_replace(
            '/^namespace\s+[^;]+;/m',
            'namespace '.$expected.';',
            $content,
            1,
            $count
        );

        if ($count === 0) {
            fwrite(STDERR, "No namespace in: {$file->getPathname()}\n");
            continue;
        }

        if ($updated !== $content) {
            file_put_contents($file->getPathname(), $updated);
            echo "Fixed: {$file->getPathname()} => {$expected}\n";
        }
    }
}

echo "Done.\n";
