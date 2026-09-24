<?php

namespace Tests\Unit\Localization;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Guards the "raw key rendered to the user" failure mode.
 *
 * LocaleParityTest compares locale files with each other, so a key that is
 * referenced from code but exists in NO locale file slips through and Laravel
 * prints the dotted key verbatim. This test walks the PHP call sites instead:
 * every literal `__()` / `trans()` / `trans_choice()` key and every
 * `'message_key' => '…'` literal must resolve in the reference locale, either as
 * a leaf value or as an array prefix.
 *
 * It also locks in the removal of the legacy nested groups in `lang/*.json`
 * (they shadowed the curated PHP namespaces for `en`/`id`/`ja`), so the packs
 * stay scalar-only like `ko`/`zh_CN`/`ar`/`ru`.
 */
class LocaleUsageTest extends TestCase
{
    /** Directories that may hold translatable call sites. */
    private const SCAN_DIRECTORIES = ['app', 'routes', 'config'];

    public function test_every_literal_translation_key_resolves_in_reference_locale(): void
    {
        $known = $this->referenceKeys();
        $unresolved = [];

        foreach ($this->scanFiles() as $path) {
            $contents = (string) file_get_contents($path);
            $relative = $this->relativePath($path);

            foreach ($this->literalKeys($contents) as $key) {
                if (! $this->resolves($key, $known)) {
                    $unresolved[$key] ??= $relative;
                }
            }
        }

        $this->assertSame(
            [],
            $unresolved,
            "Translation keys referenced from code but missing in [lang/en]:\n"
            .implode("\n", array_map(
                fn (string $key, string $file): string => "  {$key}  ({$file})",
                array_keys($unresolved),
                array_values($unresolved),
            ))
        );
    }

    public function test_message_key_literals_resolve_in_reference_locale(): void
    {
        $known = $this->referenceKeys();
        $unresolved = [];

        foreach ($this->scanFiles() as $path) {
            $contents = (string) file_get_contents($path);

            if (! str_contains($contents, 'message_key')) {
                continue;
            }

            preg_match_all("/'message_key'\s*=>\s*'([^']+)'\s*[,)]/", $contents, $matches);

            foreach ($matches[1] as $key) {
                if (! $this->resolves($key, $known)) {
                    $unresolved[$key] ??= $this->relativePath($path);
                }
            }
        }

        $this->assertSame(
            [],
            $unresolved,
            "message_key literals missing in [lang/en]:\n"
            .implode("\n", array_map(
                fn (string $key, string $file): string => "  {$key}  ({$file})",
                array_keys($unresolved),
                array_values($unresolved),
            ))
        );
    }

    /**
     * The runtime-assembled payment messages must exist for every provider the
     * concatenation can produce.
     */
    public function test_dynamic_payment_message_keys_resolve(): void
    {
        $known = $this->referenceKeys();

        $expected = [];

        foreach (['midtrans', 'ipaymu'] as $gateway) {
            foreach (['external_sync_failed', 'invoice_not_payable'] as $suffix) {
                $expected[] = "api/payments.{$gateway}.{$suffix}";
            }
        }

        foreach (['approve', 'deny', 'cancel', 'expire', 'refund'] as $label) {
            $expected[] = "api/payments.midtrans.{$label}_ok";
        }

        $missing = array_values(array_filter(
            $expected,
            fn (string $key): bool => ! $this->resolves($key, $known),
        ));

        $this->assertSame([], $missing, 'Runtime-assembled payment keys missing in [lang/en]: '.implode(', ', $missing));
    }

    /**
     * Legacy nested groups in `lang/{locale}.json` shadow the PHP namespaces:
     * Laravel's Translator checks the JSON pack first for keys without `/`, so a
     * stale JSON value silently wins over the curated PHP file. The packs must
     * stay scalar-only (package strings from laravel-lang).
     */
    public function test_locale_json_packs_contain_no_nested_groups(): void
    {
        foreach (\App\Support\Localization\LocalePreference::supported() as $locale) {
            $path = lang_path($locale.'.json');

            $this->assertFileExists($path);

            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            $this->assertIsArray($decoded, "[lang/{$locale}.json] must decode to an object.");

            $nested = array_keys(array_filter($decoded, 'is_array'));

            $this->assertSame(
                [],
                $nested,
                "[lang/{$locale}.json] must not contain nested groups (they shadow lang/{$locale}/*.php): "
                .implode(', ', $nested)
            );
        }
    }

    /**
     * @return array<string, true> dot-notation keys plus every array prefix
     */
    private function referenceKeys(): array
    {
        $keys = [];

        $this->collectPhpKeys(lang_path('en'), '', $keys);
        $this->collectJsonKeys(lang_path('en.json'), $keys);

        return $keys;
    }

    /**
     * @param  array<string, true>  $keys
     */
    private function collectPhpKeys(string $directory, string $namespace, array &$keys): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            $prefix = $namespace === ''
                ? str_replace('/', '.', substr($relative, 0, -4))
                : $namespace.'.'.str_replace('/', '.', substr($relative, 0, -4));

            $data = include $file->getPathname();

            if (is_array($data)) {
                $this->registerKeys($data, $prefix, $keys);
            }
        }
    }

    /**
     * @param  array<string, true>  $keys
     */
    private function collectJsonKeys(string $path, array &$keys): void
    {
        if (! is_file($path)) {
            return;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (is_array($data)) {
            $this->registerKeys($data, '', $keys);
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, true>  $keys
     */
    private function registerKeys(array $data, string $prefix, array &$keys): void
    {
        foreach ($data as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $keys[$dotted] = true;
                $this->registerKeys($value, $dotted, $keys);

                continue;
            }

            $keys[$dotted] = true;
        }
    }

    /**
     * @param  array<string, true>  $known
     */
    private function resolves(string $key, array $known): bool
    {
        if (isset($known[$key])) {
            return true;
        }

        return isset($known[str_replace('/', '.', $key)]);
    }

    /**
     * Literal first argument of `__()`, `trans()` and `trans_choice()` calls.
     *
     * The trailing `[,)]` requirement keeps runtime-assembled keys out of the
     * result: `__('api/payments.'.$gateway.'.x')` continues with a concatenation
     * operator instead of a delimiter, so it is verified by
     * `test_dynamic_payment_message_keys_resolve()` instead.
     *
     * @return array<int, string>
     */
    private function literalKeys(string $contents): array
    {
        preg_match_all(
            "/(?:__|trans|trans_choice)\(\s*(?:'([^']+)'|\"([^\"]+)\")\s*[,)]/",
            $contents,
            $matches,
            PREG_SET_ORDER,
        );

        $keys = [];

        foreach ($matches as $match) {
            $key = $match[1] !== '' ? $match[1] : $match[2];

            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, string>
     */
    private function scanFiles(): array
    {
        $files = [];

        foreach (self::SCAN_DIRECTORIES as $directory) {
            $absolute = base_path($directory);

            if (! is_dir($absolute)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(base_path()) + 1));
    }
}
