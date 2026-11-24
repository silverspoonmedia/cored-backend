<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Covers the `locale:remove` retirement command — the inverse of `locale:add`.
 *
 * It exercises the two steps that do not reach the network (config whitelist +
 * application-owned file deletion); the laravel-lang `lang:rm` step is skipped
 * with `--skip-package`, because the managed pack is fetched, not editable here.
 *
 * A throwaway locale code (`zz`) is registered into a backed-up copy of
 * `config/localization.php` before each case, and every artifact is restored in
 * tearDown so the repo's real lang tree and config are never mutated.
 */
class RemoveLocaleCommandTest extends TestCase
{
    private const TEST_LOCALE = 'zz';

    private string $configPath;

    private string $configBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configPath = config_path('localization.php');
        $this->configBackup = File::get($this->configPath);

        // Register the throwaway locale, plus an application-owned file and a
        // flat pack, so a removal has something real to delete.
        File::put($this->configPath, preg_replace(
            "/'supported_locales' => \[(.*?)\]/s",
            "'supported_locales' => [\$1, '".self::TEST_LOCALE."']",
            $this->configBackup,
            1,
        ));

        // The application booted before this write, so the runtime config still
        // carries the original list; keep the two in step like a real run.
        config(['localization.supported_locales' => [...$this->originalSupported(), self::TEST_LOCALE]]);

        File::ensureDirectoryExists(lang_path(self::TEST_LOCALE.'/web'));
        File::put(lang_path(self::TEST_LOCALE.'/ui.php'), "<?php\n\nreturn ['sentinel' => 'scaffolded'];\n");
        File::put(lang_path(self::TEST_LOCALE.'/web/session.php'), "<?php\n\nreturn [];\n");
        File::put(lang_path(self::TEST_LOCALE.'.json'), "{}\n");
    }

    protected function tearDown(): void
    {
        File::put($this->configPath, $this->configBackup);

        $dir = lang_path(self::TEST_LOCALE);
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }

        $json = lang_path(self::TEST_LOCALE.'.json');
        if (File::exists($json)) {
            File::delete($json);
        }

        parent::tearDown();
    }

    public function test_it_removes_the_locale_files_and_unregisters_it(): void
    {
        $this->artisan('locale:remove', [
            'locales' => [self::TEST_LOCALE],
            '--skip-package' => true,
        ])->assertSuccessful();

        // 1. Application-owned tree and the flat pack are gone.
        $this->assertDirectoryDoesNotExist(lang_path(self::TEST_LOCALE));
        $this->assertFileDoesNotExist(lang_path(self::TEST_LOCALE.'.json'));

        // 2. The whitelist no longer names it, and the other locales survive.
        $config = File::get($this->configPath);
        $this->assertStringNotContainsString("'".self::TEST_LOCALE."'", $config);

        foreach ($this->originalSupported() as $locale) {
            $this->assertStringContainsString("'".$locale."'", $config, "Removal must keep [{$locale}].");
        }

        $this->assertNotContains(self::TEST_LOCALE, config('localization.supported_locales'));
    }

    public function test_it_refuses_to_remove_the_reference_locale(): void
    {
        $this->artisan('locale:remove', [
            'locales' => ['en'],
            '--skip-package' => true,
        ])->assertFailed();

        $this->assertDirectoryExists(lang_path('en'));
        $this->assertStringContainsString("'en'", File::get($this->configPath));
    }

    public function test_it_refuses_an_unregistered_locale_without_force(): void
    {
        $this->artisan('locale:remove', [
            'locales' => ['qq'],
            '--skip-package' => true,
        ])->assertFailed();

        $this->assertStringContainsString("'".self::TEST_LOCALE."'", File::get($this->configPath));
    }

    public function test_force_removes_an_unregistered_locale(): void
    {
        File::ensureDirectoryExists(lang_path('qq'));
        File::put(lang_path('qq/ui.php'), "<?php\n\nreturn [];\n");

        try {
            $this->artisan('locale:remove', [
                'locales' => ['qq'],
                '--force' => true,
                '--skip-package' => true,
            ])->assertSuccessful();

            $this->assertDirectoryDoesNotExist(lang_path('qq'));
        } finally {
            if (File::isDirectory(lang_path('qq'))) {
                File::deleteDirectory(lang_path('qq'));
            }
        }
    }

    public function test_it_rejects_an_invalid_locale_code(): void
    {
        $this->artisan('locale:remove', [
            'locales' => ['bad/code'],
            '--skip-package' => true,
        ])->assertFailed();
    }

    public function test_it_prunes_a_packaged_domain_lang_tree(): void
    {
        // A core package ships its translations from `packages/{slug}/resources/lang`,
        // which `lang:rm` cannot see. Left behind, the retired locale rides into
        // the release mirror.
        $packageLang = base_path('packages/zz-probe-package/resources/lang/'.self::TEST_LOCALE);
        File::ensureDirectoryExists($packageLang);
        File::put($packageLang.'/api.php', "<?php\n\nreturn [];\n");

        try {
            $this->artisan('locale:remove', [
                'locales' => [self::TEST_LOCALE],
                '--skip-package' => true,
            ])->assertSuccessful();

            $this->assertDirectoryDoesNotExist($packageLang);
        } finally {
            File::deleteDirectory(base_path('packages/zz-probe-package'));
        }
    }

    public function test_it_prunes_a_vendored_package_lang_tree(): void
    {
        // The boilerplate fork is a consumer install: it has no `packages/` tree,
        // so its copy of the core package translations lives under `vendor/`.
        // Missing that root leaves the retired locale in a released consumer.
        $vendorLang = base_path('vendor/silverspoonmedia/zz-probe-package/resources/lang/'.self::TEST_LOCALE);
        File::ensureDirectoryExists($vendorLang);
        File::put($vendorLang.'/api.php', "<?php\n\nreturn [];\n");

        try {
            $this->artisan('locale:remove', [
                'locales' => [self::TEST_LOCALE],
                '--skip-package' => true,
            ])->assertSuccessful();

            $this->assertDirectoryDoesNotExist($vendorLang);
        } finally {
            File::deleteDirectory(base_path('vendor/silverspoonmedia/zz-probe-package'));
        }
    }

    /**
     * The locales the config carried before the throwaway one was appended.
     *
     * @return array<int, string>
     */
    private function originalSupported(): array
    {
        preg_match("/'supported_locales' => \[(.*?)\]/s", $this->configBackup, $m);
        preg_match_all('/[\'"]([A-Za-z0-9_-]+)[\'"]/', $m[1] ?? '', $found);

        return $found[1] ?? [];
    }
}
