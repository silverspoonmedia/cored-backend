<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * One-stop command to onboard a new application locale.
 *
 * Adding a language touches THREE places, and missing any one of them is the
 * usual source of "mixed language" bugs:
 *
 *   1. laravel-lang MANAGED files (validation.php, auth.php, *.json, …)
 *      → delegated to `php artisan lang:add` (the package owns these).
 *   2. config('localization.supported_locales')
 *      → SetLocale + LocaleController whitelist; without it the locale can
 *        never be resolved or persisted.
 *   3. APPLICATION-OWNED files (ui.php, web/*, api/*, mail.php, …)
 *      → copied from a reference locale so every key exists immediately
 *        (English fallback values), keeping LocaleParityTest green. Translate
 *        the copies afterwards.
 *
 * Example:
 *   php artisan locale:add fr
 *   php artisan locale:add de --from=en
 *   php artisan locale:add es --skip-package   # config + files only
 */
class AddLocaleCommand extends Command
{
    /** @var string */
    protected $signature = 'locale:add
        {locale : The locale code to add (e.g. fr, de, pt_BR)}
        {--from=en : Reference locale to copy application-owned files from}
        {--skip-package : Do not run laravel-lang lang:add (managed files / JSON)}
        {--force : Overwrite existing application-owned files for the new locale}';

    /** @var string */
    protected $description = 'Add a new application locale: laravel-lang files, supported_locales config, and application-owned translation scaffolding';

    public function handle(): int
    {
        $locale = trim((string) $this->argument('locale'));
        $from = trim((string) $this->option('from'));

        if (! $this->isValidLocaleCode($locale)) {
            $this->error("Invalid locale code [{$locale}]. Use letters, digits, '-' or '_' (e.g. fr, pt_BR, zh-Hans).");

            return self::FAILURE;
        }

        $supported = $this->supportedLocales();

        if (in_array($locale, $supported, true) && ! $this->option('force')) {
            $this->warn("Locale [{$locale}] is already in supported_locales. Re-run with --force to re-scaffold files.");
        }

        if (! File::isDirectory(lang_path($from))) {
            $this->error("Reference locale [{$from}] has no lang directory at ".lang_path($from).'. Pick an existing one with --from.');

            return self::FAILURE;
        }

        $this->info("Adding locale [{$locale}] (reference: [{$from}])");
        $this->newLine();

        // 1. laravel-lang managed files (validation, auth, JSON packs, …).
        if (! $this->option('skip-package')) {
            $this->components->task('Install laravel-lang managed files (lang:add)', function () use ($locale): bool {
                $exit = $this->callSilently('lang:add', ['locales' => [$locale]]);

                return $exit === self::SUCCESS;
            });
        } else {
            $this->line('  <comment>skipped</comment> laravel-lang managed files (--skip-package)');
        }

        // 2. Whitelist the locale so SetLocale / LocaleController accept it.
        $configOk = false;
        $this->components->task('Register in config/localization.php (supported_locales)', function () use ($locale, &$configOk): bool {
            $configOk = $this->addToSupportedLocales($locale);

            return $configOk;
        });

        if (! $configOk) {
            return self::FAILURE;
        }

        // 3. Scaffold application-owned files with the reference as fallback.
        $created = $this->scaffoldApplicationOwnedFiles($from, $locale);
        $this->line("  Copied <info>{$created}</info> application-owned file(s) from [{$from}] → [{$locale}]");

        $this->newLine();
        $this->components->info("Locale [{$locale}] added.");
        $this->line('Next steps:');
        $this->line("  1. Translate the copied files under <info>lang/{$locale}/</info> (they currently hold [{$from}] fallback text).");
        $this->line("  2. Translate the laravel-lang JSON pack <info>lang/{$locale}.json</info> if present.");
        $this->line('  3. Run <info>php artisan test --filter=LocaleParity</info> to confirm key parity.');
        $this->line('  4. Clear config cache if used: <info>php artisan config:clear</info>.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function supportedLocales(): array
    {
        return array_values((array) config('localization.supported_locales', []));
    }

    private function isValidLocaleCode(string $locale): bool
    {
        return $locale !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $locale) === 1;
    }

    /**
     * Append the locale to the supported_locales array in the config file,
     * editing the source so the change persists (config() is runtime-only).
     */
    private function addToSupportedLocales(string $locale): bool
    {
        $path = config_path('localization.php');

        if (! File::isFile($path)) {
            $this->error("config/localization.php not found at {$path}.");

            return false;
        }

        $contents = File::get($path);

        if (! preg_match('/([\'"])supported_locales\1\s*=>\s*\[(.*?)\]/s', $contents, $m, PREG_OFFSET_CAPTURE)) {
            $this->error('Could not locate the supported_locales array in config/localization.php. Add it manually.');

            return false;
        }

        $existing = [];
        if (preg_match_all('/[\'"]([A-Za-z0-9_-]+)[\'"]/', $m[2][0], $found)) {
            $existing = $found[1];
        }

        if (in_array($locale, $existing, true)) {
            return true; // Already present; treat as success (idempotent).
        }

        $existing[] = $locale;
        $rebuilt = "'supported_locales' => ['".implode("', '", $existing)."']";

        $fullMatchStart = $m[0][1];
        $fullMatchLength = strlen($m[0][0]);

        $updated = substr($contents, 0, $fullMatchStart)
            .$rebuilt
            .substr($contents, $fullMatchStart + $fullMatchLength);

        File::put($path, $updated);

        // Reflect immediately for the rest of this process (e.g. follow-up tasks).
        config(['localization.supported_locales' => $existing]);

        return true;
    }

    /**
     * Copy every application-owned translation file from the reference locale
     * to the new locale, preserving directory structure. Managed laravel-lang
     * files and JSON packs are skipped (owned by the package).
     */
    private function scaffoldApplicationOwnedFiles(string $from, string $locale): int
    {
        $sourceDir = lang_path($from);
        $targetDir = lang_path($locale);

        File::ensureDirectoryExists($targetDir);

        $managed = $this->managedFileNames();
        $force = (bool) $this->option('force');
        $count = 0;

        $finder = (new Finder)
            ->files()
            ->in($sourceDir)
            ->name('*.php');

        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            // Managed files live ONLY at the locale root (e.g. `auth.php`).
            // Application-owned files in subdirs (`web/auth.php`, `api/auth.php`)
            // share a basename with managed files but must NOT be skipped, so
            // match on the relative path (== filename only for root files).
            if (in_array($relative, $managed, true)) {
                continue; // laravel-lang owns these.
            }

            $destination = $targetDir.DIRECTORY_SEPARATOR.$file->getRelativePathname();

            if (File::exists($destination) && ! $force) {
                continue;
            }

            File::ensureDirectoryExists(dirname($destination));
            File::copy($file->getRealPath(), $destination);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, string>
     */
    private function managedFileNames(): array
    {
        $managed = (array) config('localization.managed_files', []);

        // Keep only concrete *.php filenames; '*.json' and globs are package /
        // JSON-pack concerns handled by lang:add.
        return array_values(array_filter(
            $managed,
            fn ($name): bool => is_string($name) && str_ends_with($name, '.php') && ! str_contains($name, '*'),
        ));
    }
}
