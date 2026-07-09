<?php

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/app/Domain', FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php' || ! str_contains($file->getPathname(), 'Controllers')) {
        continue;
    }

    $content = file_get_contents($file->getPathname());

    if (! preg_match('/extends Controller\b/', $content)) {
        continue;
    }

    if (preg_match('/use App\\\\Http\\\\Controllers\\\\(Web\\\\)?Controller;/', $content)) {
        continue;
    }

    $use = str_contains(str_replace('\\', '/', $file->getPathname()), '/Http/Controllers/Web/')
        ? 'use App\Http\Controllers\Web\Controller;'
        : 'use App\Http\Controllers\Controller;';

    $content = preg_replace(
        '/(namespace [^;]+;\R)/',
        '$1'."\n".$use."\n",
        $content,
        1
    );

    file_put_contents($file->getPathname(), $content);
    echo "Fixed controller import: {$file->getPathname()}\n";
}

echo "Done.\n";
