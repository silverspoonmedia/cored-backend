<?php

namespace Tests\Unit\Localization;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Guards the other half of `LocaleUsageTest`: a translation *file* whose keys
 * nobody addresses.
 *
 * `LocaleUsageTest` proves every referenced key exists. Nothing proved the
 * opposite, so Blade-era groups survived the Blade removal: `menu.php` and
 * `notifications.php` (static nav + bell copy, both replaced by SPA JSON),
 * `web/identity-connections.php` (the connections screen), and `web/settings.php`
 * in the cases where the SPA reads its own pack. Each was a full seven-locale
 * set — 7 files × 10 keys with a curated `// curated at` flag on every line,
 * reviewed in every wave, and rendered by nothing. The ledger said "translated";
 * the product showed no such string.
 *
 * A group with one live key is not a finding: a file is the unit of translation
 * (LocaleParityTest mirrors it across locales, the flags are per key), so the
 * file stays while any of its keys is addressed. Only zero call sites fails.
 *
 * `config/localization.php::managed_files` is exempt by definition — laravel-lang
 * ships those packs (`auth`, `pagination`, `passwords`, `validation`,
 * `http-statuses`, `actions`) and they are consumed by the framework, not by an
 * application call site we could find.
 */
class LocaleOrphanGroupTest extends TestCase
{
    /** Directories that may hold a translatable call site. */
    private const SCAN_DIRECTORIES = ['app', 'routes', 'config', 'database', 'packages', 'bootstrap', 'resources', 'tests'];

    public function test_every_host_translation_file_is_addressed_from_code(): void
    {
        $references = $this->references();

        $orphans = [];

        foreach ($this->hostFiles() as $file) {
            if ($this->isManaged($file)) {
                continue;
            }

            if (! isset($references[$file])) {
                $orphans[] = $file;
            }
        }

        $this->assertSame(
            [],
            $orphans,
            "Translation files under [lang/en] that no call site addresses (the file is reviewed in every locale wave and rendered by nothing — delete all seven, or point the surface at it):\n"
            .implode("\n", array_map(fn (string $file): string => '  lang/en/'.$file.'.php', $orphans)),
        );
    }

    /**
     * Translation files in `lang/en`, relative and without the `.php` suffix,
     * in the same address shape a call site uses (`api/payments`, `menu`).
     *
     * @return list<string>
     */
    private function hostFiles(): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(lang_path('en'), FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(lang_path('en')) + 1));
            $files[] = substr($relative, 0, -4);
        }

        sort($files);

        return $files;
    }

    /**
     * Map of translation-file address => first calling file that references it.
     *
     * Both address shapes collapse to a file: `__('api/navigation.tree_success')`
     * is `api/navigation`, `__('menu.dashboard')` is `menu`. A key assembled at
     * runtime cannot be seen here; `LocaleUsageTest` enumerates those separately.
     *
     * @return array<string, string>
     */
    private function references(): array
    {
        $references = [];

        foreach ($this->scanFiles() as $path) {
            $contents = (string) file_get_contents($path);

            preg_match_all(
                '/(?:__|trans|trans_choice|Lang::get)(?:\(\s*|::get\(\s*)[\'"]([a-zA-Z0-9_\/.-]+)[\'"]/',
                $contents,
                $matches,
            );

            foreach ($matches[1] as $key) {
                $file = $this->fileFor($key);

                if ($file !== null && ! isset($references[$file])) {
                    $references[$file] = $this->relativePath($path);
                }
            }
        }

        return $references;
    }

    private function fileFor(string $key): ?string
    {
        if (str_contains($key, '/')) {
            $segments = explode('/', $key, 2);

            if ($segments[1] === '') {
                return null;
            }

            return $segments[0].'/'.explode('.', $segments[1])[0];
        }

        if (! str_contains($key, '.')) {
            return null;
        }

        return explode('.', $key)[0];
    }

    private function isManaged(string $file): bool
    {
        $managed = (array) config('localization.managed_files', []);

        foreach ($managed as $pattern) {
            if ($pattern === '*.json') {
                continue;
            }

            if (str_replace('.php', '', $pattern) === $file) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
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
                new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
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
