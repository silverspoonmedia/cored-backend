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

    /**
     * Absolute path of a locale file, host tree first.
     */
    private function langFile(string $locale, string $relative): ?string
    {
        foreach ($this->langRoots() as $root) {
            $path = $root.'/'.$locale.'/'.$relative;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Every directory that ships application-owned translations, host first.
     *
     * Core domains extracted into Composer path packages keep their own
     * `resources/lang/{locale}/**` tree while the keys stay un-namespaced, so a
     * parity guard that only walked `lang/` would stop covering them entirely.
     *
     * @return array<int, string>
     */
    private function langRoots(): array
    {
        $roots = [lang_path()];

        foreach (glob(base_path('packages/*/resources/lang'), GLOB_ONLYDIR) ?: [] as $packageLang) {
            $roots[] = $packageLang;
        }

        return $roots;
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
            $referenceKeys = $this->flattenKeys($this->langFile($reference, $relative) ?? lang_path($reference.'/'.$relative));

            foreach ($locales as $locale) {
                if ($locale === $reference) {
                    continue;
                }

                $path = $this->langFile($locale, $relative);

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

    public function test_application_owned_placeholders_match_reference_locale(): void
    {
        $locales = $this->supportedLocales();
        $reference = $locales[0];

        foreach ($this->applicationOwnedFiles($reference) as $relative) {
            $referenceValues = $this->flattenValues($this->langFile($reference, $relative) ?? lang_path($reference.'/'.$relative));

            foreach ($locales as $locale) {
                if ($locale === $reference) {
                    continue;
                }

                $path = $this->langFile($locale, $relative);
                if (! is_file($path)) {
                    continue;
                }

                $values = $this->flattenValues($path);
                foreach ($referenceValues as $key => $referenceValue) {
                    if (! is_string($referenceValue) || ! array_key_exists($key, $values) || ! is_string($values[$key])) {
                        continue;
                    }

                    if (str_ends_with($relative, 'validation.php')) {
                        continue;
                    }

                    $this->assertSame(
                        $this->placeholders($referenceValue),
                        $this->placeholders($values[$key]),
                        "[{$locale}/{$relative}.{$key}] placeholders differ from [{$reference}]."
                    );
                }
            }
        }
    }

    /**
     * A literal key written twice inside the same array block is invisible to
     * every other guard: the file parses, `flattenKeys()` sees the surviving
     * entry only, and PHP silently keeps the *last* value. The rendered string is
     * then simply the wrong one, with no missing key and no parity mismatch to
     * report it. The curation appliers can produce this by accident when a create
     * path inserts a key that already exists, so it is asserted, not assumed.
     *
     * Covers managed packs too: `validation.php` is where an added-on-top key
     * (like `attributes.h-captcha-response`) is most likely to collide with a
     * later `lang:update`.
     */
    public function test_no_lang_file_declares_a_literal_key_twice(): void
    {
        $duplicates = [];

        foreach ($this->supportedLocales() as $locale) {
            foreach ($this->langRoots() as $langRoot) {
                $base = $langRoot.'/'.$locale;

                if (! is_dir($base)) {
                    continue;
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    foreach ($this->duplicateLiteralKeys($file->getPathname()) as $duplicate) {
                        $duplicates[] = "{$locale}/"
                            .str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1))
                            .'  '.$duplicate;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $duplicates,
            "Lang files declaring the same literal key twice (PHP keeps the last value, so the other is dead):\n"
            .implode("\n", $duplicates)
        );
    }

    /**
     * Literal keys repeated inside one array block of a PHP lang file.
     *
     * Tokenized rather than regex-scanned, because the nesting is what makes a
     * repeat meaningful: the same key name in two sibling arrays (`steps.1.title`
     * and `steps.2.title`) is correct, while a repeat in one array is not.
     * Indentation cannot be trusted to tell those apart, since a container array
     * and its parent's other keys share a depth.
     *
     * @return array<int, string>
     */
    private function duplicateLiteralKeys(string $path): array
    {
        $tokens = token_get_all((string) file_get_contents($path));
        $stack = [[]];
        $pendingKey = null;
        $duplicates = [];

        foreach ($tokens as $token) {
            if (is_array($token)) {
                [$id, $text, $line] = $token;

                if ($id === T_CONSTANT_ENCAPSED_STRING && $pendingKey === null) {
                    $pendingKey = [stripcslashes(substr($text, 1, -1)), $line];

                    continue;
                }

                if ($id === T_DOUBLE_ARROW && $pendingKey !== null) {
                    $frame = count($stack) - 1;
                    $key = $pendingKey[0];

                    if (isset($stack[$frame][$key])) {
                        $duplicates[] = "'{$key}' at line {$pendingKey[1]} repeats line {$stack[$frame][$key]}";
                    } else {
                        $stack[$frame][$key] = $pendingKey[1];
                    }

                    $pendingKey = null;

                    continue;
                }

                if (! in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $pendingKey = null;
                }

                continue;
            }

            if ($token === '[') {
                $stack[] = [];
            } elseif ($token === ']') {
                array_pop($stack);
            }

            $pendingKey = null;
        }

        return $duplicates;
    }

    /**
     * @return array<int, string>
     */
    private function supportedLocales(): array
    {
        return \App\Support\Localization\LocalePreference::supported();
    }

    /**
     * Relative paths (e.g. `api/users.php`, `web/auth.php`) of every
     * application-owned PHP lang file in a locale directory.
     *
     * @return array<int, string>
     */
    private function applicationOwnedFiles(string $locale): array
    {
        $out = [];

        foreach ($this->langRoots() as $langRoot) {
            $base = $langRoot.'/'.$locale;

            if (! is_dir($base)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));

                if (! str_contains($relative, '/') && in_array($relative, self::MANAGED_FILES, true)) {
                    continue;
                }

                $out[$relative] = true;
            }
        }

        $out = array_keys($out);
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
        return array_keys($this->flattenValues($file));
    }

    /**
     * Dot-notation values of a PHP array lang file.
     *
     * @return array<string, mixed>
     */
    private function flattenValues(string $file): array
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
            $out[implode('.', $segments)] = $value;
        }

        ksort($out);

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function placeholders(string $value): array
    {
        preg_match_all('/(?<![A-Za-z0-9_]):[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
        $placeholders = array_unique($matches[0]);
        sort($placeholders);

        return $placeholders;
    }
}
