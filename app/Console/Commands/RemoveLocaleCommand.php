<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The inverse of `locale:add`: retire a locale from the application.
 *
 * Removing a language is the same three places `locale:add` touches, and
 * missing one leaves a half-retired locale that every guard reports forever:
 *
 *   1. laravel-lang MANAGED files (validation.php, auth.php, *.json, …)
 *      → delegated to `php artisan lang:rm` (the package owns these).
 *   2. config('localization.supported_locales')
 *      → SetLocale + LocaleController whitelist; leaving the code there keeps
 *        resolving a locale whose files no longer exist (raw keys to the user).
 *   3. APPLICATION-OWNED files (ui.php, web/*, api/*, …)
 *      → `lang:rm` only knows the managed names, so the rest of
 *        `lang/{locale}/` is deleted here.
 *
 * The reference locale (`en`, the first entry — English is the source) and the
 * last remaining locale are refused: either one empties the application.
 *
 * Example:
 *   php artisan locale:remove ja ko zh_CN ar ru
 *   php artisan locale:remove fr --force      # not in the whitelist
 */
class RemoveLocaleCommand extends Command
{
    /** @var string */
    protected $signature = 'locale:remove
        {locales* : Locale codes to retire (e.g. ja ko zh_CN)}
        {--force : Proceed even when the locale is not in supported_locales}
        {--skip-package : Do not run laravel-lang lang:rm (managed files)}
        {--skip-host-packages : Do not prune packages/*/resources/lang or vendor/silverspoonmedia/*/resources/lang}';

    /** @var string */
    protected $description = 'Remove application locales: laravel-lang files, supported_locales config, and application-owned translation files';

    public function handle(): int
    {
        $locales = array_values(array_unique(array_map(
            static fn ($locale): string => trim((string) $locale),
            (array) $this->argument('locales'),
        )));

        foreach ($locales as $locale) {
            if (! preg_match('/^[A-Za-z0-9_-]+$/', $locale)) {
                $this->error("Invalid locale code [{$locale}]. Use letters, digits, '-' or '_' (e.g. ja, pt_BR, zh-Hans).");

                return self::FAILURE;
            }
        }

        $supported = $this->supportedLocales();
        $reference = $supported[0] ?? 'en';

        if (in_array($reference, $locales, true)) {
            $this->error("Refusing to remove the reference locale [{$reference}]: it is the source every other locale is checked against.");

            return self::FAILURE;
        }

        $remaining = array_values(array_diff($supported, $locales));

        if ($remaining === []) {
            $this->error('Refusing to remove every supported locale: at least one must stay in config/localization.php.');

            return self::FAILURE;
        }

        $unregistered = array_values(array_diff($locales, $supported));

        if ($unregistered !== [] && ! $this->option('force')) {
            $this->error('Not in supported_locales: '.implode(', ', $unregistered).'. Re-run with --force to delete their files anyway.');

            return self::FAILURE;
        }

        $this->info('Removing locale(s) ['.implode(', ', $locales).'] (reference: ['.$reference.'])');
        $this->newLine();

        $packageFailed = [];

        foreach ($locales as $locale) {
            // 1. laravel-lang managed files (validation, auth, JSON packs, …).
            if (! $this->option('skip-package')) {
                $ok = false;
                $this->components->task("Remove laravel-lang managed files for [{$locale}] (lang:rm)", function () use ($locale, &$ok): bool {
                    $exit = $this->callSilently('lang:rm', ['locales' => [$locale], '--force' => true]);
                    $ok = $exit === self::SUCCESS;

                    return $ok;
                });

                if (! $ok) {
                    $packageFailed[] = $locale;
                }
            } else {
                $this->line("  <comment>skipped</comment> laravel-lang managed files for [{$locale}] (--skip-package)");
            }

            // 2. Everything laravel-lang does not own: the rest of the tree and
            //    the flat JSON pack. Runs after lang:rm so a managed-only locale
            //    still ends with no directory at all.
            $deleted = $this->removeApplicationOwnedFiles($locale);
            $this->line("  Deleted <info>{$deleted}</info> application-owned path(s) for [{$locale}]");

            // 3. Core packages ship their own lang tree (`resources/lang`,
            //    loaded via addPath). A path-repo checkout owns it here; a
            //    consumer install has no `packages/` directory at all, so its
            //    pruned copies arrive with the next `composer update`.
            if (! $this->option('skip-host-packages')) {
                $packagePaths = $this->removePackagedLocaleTrees($locale);

                if ($packagePaths > 0) {
                    $this->line("  Deleted <info>{$packagePaths}</info> packaged lang tree(s) for [{$locale}]");
                }
            }
        }

        // 3. Whitelist: drop the locale so SetLocale / LocaleController refuse it.
        $this->components->task('Unregister from config/localization.php (supported_locales)', function () use ($locales): bool {
            return $this->removeFromSupportedLocales($locales);
        });

        $this->newLine();

        if ($packageFailed !== []) {
            $this->components->error('laravel-lang lang:rm failed for: '.implode(', ', $packageFailed));

            return self::FAILURE;
        }

        $this->components->info('Locale(s) ['.implode(', ', $locales).'] removed.');
        $this->line('Next steps:');
        $this->line('  1. Grep the retired codes in `lang/`, `packages/*/resources/lang`, and the sibling SPA `src/i18n/locales/`.');
        $this->line('  2. Run the locale guards: <info>php scripts/locale-prose-scan.php</info> and <info>php scripts/locale-rawkey-scan.php</info>.');
        $this->line('  3. Run <info>php artisan test --filter=Locale</info> to confirm parity across the remaining locales.');
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

    /**
     * Delete `lang/{locale}/` (whatever is left after `lang:rm`) and the flat
     * `lang/{locale}.json` pack.
     *
     * @return int Number of paths deleted.
     */
    private function removeApplicationOwnedFiles(string $locale): int
    {
        $deleted = 0;

        $dir = lang_path($locale);

        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
            $deleted++;
        }

        $json = lang_path($locale.'.json');

        if (File::exists($json)) {
            File::delete($json);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Drop the locale from every core package's own `resources/lang` tree.
     *
     * Core domains shipped as Composer packages keep their translations in
     * `resources/lang/{locale}/**`, loaded through `addPath()` so the keys stay
     * un-namespaced — a path repository here (`packages/*`), an installed
     * dependency in a consumer (`vendor/silverspoonmedia/*`). `lang:rm` only
     * sees `lang_path()`, so without this the retired locale stays and the
     * release mirrors would keep shipping it.
     *
     * @return int Number of package trees touched.
     */
    private function removePackagedLocaleTrees(string $locale): int
    {
        $touched = 0;

        // Path checkout first, then an installed consumer: the fork has no
        // `packages/` directory, so its pruned copy arrives through Composer.
        $roots = [
            base_path('packages/*/resources/lang/'.$locale),
            base_path('vendor/silverspoonmedia/*/resources/lang/'.$locale),
        ];

        $dirs = array_merge(...array_map(
            static fn (string $pattern): array => glob($pattern, GLOB_ONLYDIR) ?: [],
            $roots,
        ));

        foreach ($dirs as $dir) {
            File::deleteDirectory($dir);
            $touched++;
        }

        return $touched;
    }

    /**
     * Drop the locales from the supported_locales array in the config file,
     * editing the source so the change persists (config() is runtime-only).
     *
     * @param  array<int, string>  $locales
     */
    private function removeFromSupportedLocales(array $locales): bool
    {
        $path = config_path('localization.php');

        if (! File::isFile($path)) {
            $this->error("config/localization.php not found at {$path}.");

            return false;
        }

        $contents = File::get($path);

        if (! preg_match('/([\'"])supported_locales\1\s*=>\s*\[(.*?)\]/s', $contents, $m, PREG_OFFSET_CAPTURE)) {
            $this->error('Could not locate the supported_locales array in config/localization.php. Edit it manually.');

            return false;
        }

        $existing = [];
        if (preg_match_all('/[\'"]([A-Za-z0-9_-]+)[\'"]/', $m[2][0], $found)) {
            $existing = $found[1];
        }

        $kept = array_values(array_diff($existing, $locales));

        if ($kept === []) {
            $this->error('Refusing to write an empty supported_locales array. Edit config/localization.php manually if that is really intended.');

            return false;
        }

        $rebuilt = "'supported_locales' => ['".implode("', '", $kept)."']";

        $fullMatchStart = $m[0][1];
        $fullMatchLength = strlen($m[0][0]);

        $updated = substr($contents, 0, $fullMatchStart)
            .$rebuilt
            .substr($contents, $fullMatchStart + $fullMatchLength);

        File::put($path, $updated);

        // Reflect immediately for the rest of this process (e.g. follow-up tasks).
        config(['localization.supported_locales' => $kept]);

        return true;
    }
}
