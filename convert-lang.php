<?php
/**
 * Convert Laravel PHP translation files to nested JSON for vue-i18n.
 * Output: lang/{locale}.json with nested structure matching directory/file structure.
 * Usage: php convert-lang.php
 */

$baseDir = __DIR__ . '/lang';
$outputDir = $baseDir; // Output to lang/{locale}.json
$locales = ['en', 'id', 'ja'];

foreach ($locales as $locale) {
    $localeDir = $baseDir . '/' . $locale;
    if (!is_dir($localeDir)) {
        echo "Skipping $locale: directory not found\n";
        continue;
    }

    $messages = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($localeDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') continue;

        $relativePath = substr($file->getPathname(), strlen($localeDir) + 1);
        // Convert Windows backslashes to forward slashes
        $relativePath = str_replace('\\', '/', $relativePath);
        // Remove .php extension
        $keyPath = substr($relativePath, 0, -4);
        // Split into segments
        $segments = explode('/', $keyPath);

        $data = include $file->getPathname();
        if (!is_array($data)) continue;

        // Build nested structure
        $current = &$messages;
        foreach ($segments as $i => $segment) {
            if (!isset($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }
        $current = $data;
        unset($current);
    }

    $json = json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $outputFile = $outputDir . '/' . $locale . '.json';
    file_put_contents($outputFile, $json);
    $keyCount = count($messages, COUNT_RECURSIVE);
    echo "Wrote $outputFile ($keyCount keys)\n";
}

echo "Done.\n";
