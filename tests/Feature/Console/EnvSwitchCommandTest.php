<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Covers env:switch — preserves per-profile snapshots while swapping active .env.
 */
class EnvSwitchCommandTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $backups = [];

    /** @var array<int, string> */
    private const MANAGED_FILES = [
        '.env',
        '.env.profile',
        '.env.local',
        '.env.staging',
        '.env.production',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::MANAGED_FILES as $file) {
            $path = base_path($file);
            $this->backups[$file] = File::exists($path) ? File::get($path) : null;
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (self::MANAGED_FILES as $file) {
            $path = base_path($file);

            if (File::exists($path)) {
                File::delete($path);
            }

            if ($this->backups[$file] !== null) {
                File::put($path, $this->backups[$file]);
            }
        }

        parent::tearDown();
    }

    public function test_it_switches_between_profiles_and_preserves_snapshots(): void
    {
        File::put(base_path('.env'), implode("\n", [
            'APP_NAME="Laravel"',
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'APP_DEBUG=true',
            'DB_DATABASE="monolith_local"',
        ]));
        File::put(base_path('.env.profile'), "local\n");
        File::put(base_path('bootstrap/cache/config.php'), '<?php return [];');

        $this->artisan('env:switch', ['profile' => 'staging'])
            ->assertSuccessful();

        $this->assertFileDoesNotExist(base_path('bootstrap/cache/config.php'));
        $this->assertStringContainsString('APP_ENV="staging"', File::get(base_path('.env')));
        $this->assertStringContainsString('monolith_local', File::get(base_path('.env.local')));

        File::put(base_path('.env'), str_replace(
            'DB_DATABASE="monolith_staging"',
            'DB_DATABASE="monolith_staging_custom"',
            File::get(base_path('.env')),
        ));

        $this->artisan('env:switch', ['profile' => 'local'])
            ->assertSuccessful();

        $this->assertStringContainsString('monolith_local', File::get(base_path('.env')));
        $this->assertStringContainsString('monolith_staging_custom', File::get(base_path('.env.staging')));
        $this->assertSame('local', trim(File::get(base_path('.env.profile'))));
    }

    public function test_it_bootstraps_staging_from_template_on_first_switch(): void
    {
        File::put(base_path('.env'), implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'DB_DATABASE="monolith_local"',
        ]));
        File::put(base_path('.env.profile'), "local\n");

        $this->artisan('env:switch', ['profile' => 'staging'])
            ->assertSuccessful();

        $this->assertFileExists(base_path('.env.staging'));
        $this->assertStringContainsString('APP_ENV="staging"', File::get(base_path('.env')));
        $this->assertMatchesRegularExpression(
            '/^ENCRYPTION_BLIND_INDEX_PEPPER="[a-f0-9]{64}"$/m',
            File::get(base_path('.env')),
        );
    }

    public function test_it_shows_current_profile_when_called_without_argument(): void
    {
        File::put(base_path('.env'), "APP_ENV=\"local\"\n");
        File::put(base_path('.env.profile'), "local\n");

        $this->artisan('env:switch')
            ->expectsOutputToContain('local')
            ->assertSuccessful();
    }

    public function test_it_warns_when_switching_to_the_same_profile(): void
    {
        File::put(base_path('.env'), implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'DB_DATABASE="monolith_local"',
        ]));
        File::put(base_path('.env.local'), File::get(base_path('.env')));
        File::put(base_path('.env.profile'), "local\n");

        $this->artisan('env:switch', ['profile' => 'local'])
            ->expectsOutputToContain('Already on [local]')
            ->assertSuccessful();

        $this->assertStringContainsString('monolith_local', File::get(base_path('.env')));
    }

    public function test_list_option_shows_profile_statuses(): void
    {
        File::put(base_path('.env'), "APP_ENV=\"local\"\n");
        File::put(base_path('.env.profile'), "local\n");
        File::put(base_path('.env.local'), File::get(base_path('.env')));

        $this->artisan('env:switch', ['--list' => true])
            ->expectsTable(['Profile', 'Active', 'Snapshot'], [
                ['local', 'yes', 'yes'],
                ['staging', 'no', 'no'],
                ['production', 'no', 'no'],
            ])
            ->assertSuccessful();
    }
}
