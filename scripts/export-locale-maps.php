<?php

$root = dirname(__DIR__);
$locales = ['en', 'id', 'ja', 'ko', 'zh_CN', 'ar', 'ru'];
$out = [];

function flattenLocaleValue(mixed $value, string $prefix = '', array &$out = []): void
{
    if (is_array($value)) {
        foreach ($value as $key => $child) {
            flattenLocaleValue($child, $prefix === '' ? (string) $key : $prefix.'.'.$key, $out);
        }

        return;
    }

    $out[$prefix] = $value;
}

foreach ($locales as $locale) {
    $out[$locale] = [];
    $base = $root.'/lang/'.$locale;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
        $namespace = preg_replace('/\.php$/', '', $relative);
        $namespace = str_replace('/', '.', (string) $namespace);
        $values = [];
        flattenLocaleValue(include $file->getPathname(), '', $values);
        foreach ($values as $key => $value) {
            $out[$locale][$namespace.'.'.$key] = $value;
        }
    }
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
