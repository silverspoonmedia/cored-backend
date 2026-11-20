<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Covers the `locale:add` onboarding command. It exercises the two
 * side-effecting steps that have no network dependency (config whitelist +
 * application-owned file scaffolding); the laravel-lang `lang:add` step is
 * skipped here with --skip-package because it reaches out to fetch managed
 * packs.
 *
 * A throwaway locale code (`zz`) is used and every artifact is restored in
 * tearDown so the repo's real lang files and config are never mutated.
 */
class AddLocaleCommandTest extends TestCase
{
    private const TEST_LOCALE = 'zz';

    private string $configPath;

    private string $configBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configPath = config_path('localization.php');
        $this->configBackup = File::get($this->configPath);
    }

    protected function tearDown(): void
    {
        // Restore the config file and remove any scaffolded locale dir/JSON.
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

    public function test_it_registers_the_locale_and_scaffolds_application_owned_files(): void
    {
        $this->artisan('locale:add', [
            'locale' => self::TEST_LOCALE,
            '--from' => 'en',
            '--skip-package' => true,
        ])->assertSuccessful();

        // 1. Config whitelist updated in the source file (persists).
        $this->assertStringContainsString("'".self::TEST_LOCALE."'", File::get($this->configPath));

        // 2. Application-owned files copied, preserving nested structure.
        $this->assertFileExists(lang_path(self::TEST_LOCALE.'/ui.php'));
        $this->assertFileExists(lang_path(self::TEST_LOCALE.'/mail.php'));
        $this->assertFileExists(lang_path(self::TEST_LOCALE.'/web/session.php'));

        // Identity's `api/users.php` now ships with `silverspoonmedia/platform`,
        // so it is not the host's to scaffold — the kernel's own `resources/lang`
        // carries it for every locale.
        $this->assertFileDoesNotExist(lang_path(self::TEST_LOCALE.'/api/users.php'));

        // 3. Managed laravel-lang files are NOT copied (the package owns them).
        $this->assertFileDoesNotExist(lang_path(self::TEST_LOCALE.'/validation.php'));
        $this->assertFileDoesNotExist(lang_path(self::TEST_LOCALE.'/auth.php'));
    }

    public function test_it_rejects_an_invalid_locale_code(): void
    {
        $this->artisan('locale:add', [
            'locale' => 'bad/code',
            '--skip-package' => true,
        ])->assertFailed();

        $this->assertDirectoryDoesNotExist(lang_path('bad/code'));
    }

    public function test_it_fails_when_reference_locale_is_missing(): void
    {
        $this->artisan('locale:add', [
            'locale' => self::TEST_LOCALE,
            '--from' => 'nope',
            '--skip-package' => true,
        ])->assertFailed();
    }

    public function test_scaffolding_is_idempotent_without_force(): void
    {
        $args = [
            'locale' => self::TEST_LOCALE,
            '--from' => 'en',
            '--skip-package' => true,
        ];

        $this->artisan('locale:add', $args)->assertSuccessful();

        // Mutate a copied file; a second run without --force must not clobber it.
        $uiPath = lang_path(self::TEST_LOCALE.'/ui.php');
        File::put($uiPath, "<?php\n\nreturn ['sentinel' => 'kept'];\n");

        $this->artisan('locale:add', $args)->assertSuccessful();

        $this->assertStringContainsString('sentinel', File::get($uiPath));
    }
}
