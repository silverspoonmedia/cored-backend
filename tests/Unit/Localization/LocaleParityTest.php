<?php

namespace Tests\Unit\Localization;

use FilesystemIterator;
use RecursiveArrayIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Multi-language safety net. For a project meant to serve many locales, the
 * silent failure mode is a key that exists in one locale but not another:
 * Laravel falls back to the default locale, so the user sees mixed languages
 * with no error. This test fails the build the moment any application-owned
 * lang file or key is missing from a supported locale.
 *
 * Scope: only APPLICATION-OWNED files. Package-managed files (laravel-lang:
 * validation.php, auth.php, *.json, …) are intentionally excluded — they are
 * synced by `php artisan lang:update` and may legitimately differ.
 */
class LocaleParityTest extends TestCase
{
    /**
     * Files maintained by laravel-lang (config('localization.managed_files')),
     * excluded from parity. JSON packs are handled by the package too.
     *
     * @var array<int, string>
     */
    private const MANAGED_FILES = [
        'actions.php',
        'auth.php',
        'http-statuses.php',
        'pagination.php',
        'passwords.php',
        'validation.php',
    ];

    public function test_every_supported_locale_has_a_lang_directory(): void
    {
        foreach ($this->supportedLocales() as $locale) {
            $this->assertDirectoryExists(
                lang_path($locale),
                "Missing lang directory for supported locale [{$locale}]."
            );
        }
    }

    public function test_application_owned_files_exist_in_every_locale(): void
    {
        $reference = $this->supportedLocales()[0];
        $referenceFiles = $this->applicationOwnedFiles($reference);

        foreach ($this->supportedLocales() as $locale) {
            $localeFiles = $this->applicationOwnedFiles($locale);

            $this->assertSame(
                [],
                array_values(array_diff($referenceFiles, $localeFiles)),
                "Locale [{$locale}] is missing application-owned files present in [{$reference}]."
            );
            $this->assertSame(
                [],
                array_values(array_diff($localeFiles, $referenceFiles)),
                "Locale [{$locale}] has application-owned files absent from [{$reference}]."
            );
        }
    }

    public function test_application_owned_keys_match_across_locales(): void
    {
        $locales = $this->supportedLocales();
        $reference = $locales[0];

        foreach ($this->applicationOwnedFiles($reference) as $relative) {
            $referenceKeys = $this->flattenKeys(lang_path($reference.'/'.$relative));

            foreach ($locales as $locale) {
                if ($locale === $reference) {
                    continue;
                }

                $path = lang_path($locale.'/'.$relative);

                if (! is_file($path)) {
                    // Covered by the file-existence test; skip to avoid noise.
                    continue;
                }

                $keys = $this->flattenKeys($path);

                $missing = array_values(array_diff($referenceKeys, $keys));
                $extra = array_values(array_diff($keys, $referenceKeys));

                $this->assertSame([], $missing, "[{$locale}/{$relative}] is missing keys present in [{$reference}]: ".implode(', ', $missing));
                $this->assertSame([], $extra, "[{$locale}/{$relative}] has keys absent from [{$reference}]: ".implode(', ', $extra));
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function supportedLocales(): array
    {
        return array_values((array) config('localization.supported_locales', ['en', 'id']));
    }

    /**
     * Relative paths (e.g. `api/users.php`, `web/auth.php`) of every
     * application-owned PHP lang file in a locale directory.
     *
     * @return array<int, string>
     */
    private function applicationOwnedFiles(string $locale): array
    {
        $base = lang_path($locale);

        if (! is_dir($base)) {
            return [];
        }

        $out = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));

            if (in_array(basename($relative), self::MANAGED_FILES, true)) {
                continue;
            }

            $out[] = $relative;
        }

        sort($out);

        return $out;
    }

    /**
     * Dot-notation keys of a PHP array lang file.
     *
     * @return array<int, string>
     */
    private function flattenKeys(string $file): array
    {
        $data = include $file;

        if (! is_array($data)) {
            return [];
        }

        $out = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($data));

        foreach ($iterator as $value) {
            $segments = [];
            for ($depth = 0; $depth <= $iterator->getDepth(); $depth++) {
                $segments[] = $iterator->getSubIterator($depth)->key();
            }
            $out[] = implode('.', $segments);
        }

        sort($out);

        return $out;
    }
}
