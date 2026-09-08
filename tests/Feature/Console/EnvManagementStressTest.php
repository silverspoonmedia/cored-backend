<?php

namespace Tests\Feature\Console;

use App\Support\EnvironmentBackupManager;
use App\Support\EnvironmentCurator;
use App\Support\EnvironmentKeyWriter;
use App\Support\EnvironmentMutationGuard;
use App\Support\EnvironmentProfileManager;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * Stress / edge-case QA for env management (normal → hard → insane).
 * Runs in an isolated sandbox; does not touch the developer's active .env.
 */
class EnvManagementStressTest extends TestCase
{
    private string $sandbox;

    private string $storage;

    private EnvironmentBackupManager $backups;

    private EnvironmentMutationGuard $guard;

    private EnvironmentProfileManager $profiles;

    private EnvironmentCurator $curator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = storage_path('framework/testing-env-stress-'.uniqid());
        $this->storage = $this->sandbox.DIRECTORY_SEPARATOR.'storage';
        File::makeDirectory($this->storage, recursive: true);

        $this->backups = new EnvironmentBackupManager($this->storage, $this->sandbox);
        $this->guard = new EnvironmentMutationGuard($this->backups);
        $this->profiles = new EnvironmentProfileManager(
            new EnvironmentKeyWriter,
            $this->guard,
            $this->sandbox,
        );
        $this->curator = new EnvironmentCurator($this->profiles, $this->guard);

        $this->app->instance(EnvironmentBackupManager::class, $this->backups);
        $this->app->instance(EnvironmentMutationGuard::class, $this->guard);
        $this->app->instance(EnvironmentProfileManager::class, $this->profiles);
        $this->app->instance(EnvironmentCurator::class, $this->curator);

        File::copy(base_path('.env.local.example'), $this->profiles->templatePath('local'));
        File::copy(base_path('.env.staging.example'), $this->profiles->templatePath('staging'));
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->sandbox)) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    // --- Tier 1: Normal ---

    public function test_n01_fresh_install_generates_secrets_without_pending_backup(): void
    {
        $this->artisan('env:install', ['profile' => 'local', '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertFileExists($this->profiles->envPath());
        $this->assertFileExists($this->profiles->profilePath('local'));
        $this->assertSame('local', trim(File::get($this->profiles->markerPath())));
        $this->assertFalse($this->backups->hasPending());
        $this->assertMatchesRegularExpression('/^APP_KEY="base64:.+"$/m', File::get($this->profiles->envPath()));
    }

    public function test_n02_switch_round_trip_preserves_per_profile_db_names(): void
    {
        $this->seedEnv([
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="pre-seeded-jwt-secret-value"',
            'DB_DATABASE="monolith_local_custom"',
        ]);
        $this->profiles->setActiveProfile('local');
        $this->profiles->saveSnapshot('local');

        $this->artisan('env:switch', ['profile' => 'staging', '--no-interaction' => true])
            ->assertSuccessful();

        File::put($this->profiles->envPath(), str_replace(
            'DB_DATABASE="monolith_staging"',
            'DB_DATABASE="monolith_staging_custom"',
            File::get($this->profiles->envPath()),
        ));

        $this->artisan('env:switch', ['profile' => 'local', '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertStringContainsString('monolith_local_custom', File::get($this->profiles->envPath()));
        $this->assertStringContainsString('monolith_staging_custom', File::get($this->profiles->profilePath('staging')));
    }

    public function test_n03_curate_dry_run_does_not_write(): void
    {
        $this->seedEnvWithMarker('local', "APP_ENV=\"local\"\nDB_DATABASE=\"dry_run_db\"\n");
        $before = File::get($this->profiles->envPath());

        $this->artisan('env:curate', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame($before, File::get($this->profiles->envPath()));
    }

    public function test_n04_curate_reorders_while_preserving_custom_values(): void
    {
        $this->seedEnvWithMarker('local', implode("\n", [
            'DB_DATABASE="curated_custom"',
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="curated-jwt-secret"',
        ]));
        $this->profiles->saveSnapshot('local');

        $this->artisan('env:curate', ['--no-interaction' => true])
            ->assertSuccessful();

        $env = File::get($this->profiles->envPath());
        $this->assertStringContainsString('DB_DATABASE="curated_custom"', $env);
        $this->assertStringContainsString('# --- Application', $env);
        $this->assertLessThan(
            strpos($env, 'DB_DATABASE='),
            strpos($env, '# --- Application'),
        );
    }

    public function test_n05_restore_list_shows_no_pending_when_clean(): void
    {
        $this->artisan('env:restore', ['--list' => true])
            ->expectsOutputToContain('No pending')
            ->assertSuccessful();
    }

    public function test_n07_switch_same_profile_is_no_op(): void
    {
        $this->seedEnvWithMarker('local', implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="noop-jwt"',
            'DB_DATABASE="noop_db"',
        ]));
        $this->profiles->saveSnapshot('local');
        $before = File::get($this->profiles->envPath());

        $this->artisan('env:switch', ['profile' => 'local', '--no-interaction' => true])
            ->expectsOutputToContain('Already on [local]')
            ->assertSuccessful();

        $this->assertSame($before, File::get($this->profiles->envPath()));
    }

    public function test_n08_key_writer_mutation_uses_guard_backup(): void
    {
        $envPath = $this->profiles->envPath();
        File::put($envPath, "APP_KEY=\"\"\nAPP_PREVIOUS_KEYS=\"\"\n");

        $writer = new EnvironmentKeyWriter;
        $this->guard->run(
            'key:generate',
            fn (): string => $writer->writeAppKey($envPath),
            [$envPath => '.env'],
        );

        $this->assertMatchesRegularExpression('/^APP_KEY="base64:.+"$/m', File::get($envPath));
        $this->assertFalse($this->backups->hasPending());
    }

    // --- Tier 2: Hard ---

    public function test_h01_guard_rollback_on_failure_keeps_pending(): void
    {
        $envPath = $this->profiles->envPath();
        File::put($envPath, "APP_ENV=\"local\"\n");

        try {
            $this->guard->run(
                'test-failure',
                function () use ($envPath): void {
                    File::put($envPath, "APP_ENV=\"broken\"\n");
                    throw new RuntimeException('simulated failure');
                },
                [$envPath => '.env'],
            );
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('simulated failure', $exception->getMessage());
        }

        $this->assertStringContainsString('APP_ENV="local"', File::get($envPath));
        $this->assertTrue($this->backups->hasPending());
    }

    public function test_h02_pending_backup_blocks_install(): void
    {
        $envPath = $this->profiles->envPath();
        File::put($envPath, "APP_ENV=\"local\"\n");
        $this->backups->createPending('stale', [$envPath => '.env']);

        $this->artisan('env:install', ['profile' => 'local', '--force' => true, '--no-interaction' => true])
            ->assertFailed();
    }

    public function test_h03_restore_keeps_pending_until_discard(): void
    {
        $envPath = $this->profiles->envPath();
        File::put($envPath, "APP_ENV=\"local\"\nORIGINAL=true\n");
        $pending = $this->backups->createPending('manual', [$envPath => '.env']);
        File::put($envPath, "APP_ENV=\"mutated\"\n");

        $this->artisan('env:restore', ['id' => $pending->id])
            ->assertSuccessful();

        $this->assertStringContainsString('ORIGINAL=true', File::get($envPath));
        $this->assertTrue($this->backups->hasPending());

        $this->artisan('env:restore', ['--discard' => true])
            ->assertSuccessful();

        $this->assertFalse($this->backups->hasPending());
    }

    public function test_h05_install_force_backs_up_env_and_snapshot(): void
    {
        $this->seedEnvWithMarker('local', implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="force-backup-jwt"',
            'DB_DATABASE="before_force"',
        ]));
        $this->profiles->saveSnapshot('local');

        $this->artisan('env:install', [
            'profile' => 'local',
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertStringNotContainsString('before_force', File::get($this->profiles->envPath()));
        $this->assertFalse($this->backups->hasPending());
    }

    public function test_h06_curate_sync_snapshot_updates_snapshot_file(): void
    {
        $this->seedEnvWithMarker('local', implode("\n", [
            'DB_DATABASE="sync_me"',
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="sync-jwt"',
        ]));
        $this->profiles->saveSnapshot('local');

        $this->artisan('env:curate', ['--sync-snapshot' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertSame(
            File::get($this->profiles->envPath()),
            File::get($this->profiles->profilePath('local')),
        );
    }

    public function test_h07_active_profile_inferred_from_app_env_when_marker_missing(): void
    {
        $this->seedEnv([
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
        ]);

        $this->assertSame('local', $this->profiles->activeProfile());
    }

    public function test_h08_invalid_marker_falls_back_to_app_env(): void
    {
        $this->seedEnv(['APP_ENV="staging"', 'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"', 'APP_PREVIOUS_KEYS=""']);
        File::put($this->profiles->markerPath(), "invalid-profile\n");

        $this->assertSame('staging', $this->profiles->activeProfile());
    }

    public function test_h09_manual_backup_create_blocks_until_discard(): void
    {
        $this->seedEnv(['APP_ENV="local"', 'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"', 'APP_PREVIOUS_KEYS=""']);

        $this->artisan('env:backup:create')
            ->assertSuccessful();

        $this->artisan('env:backup:create')
            ->assertFailed();

        $this->artisan('env:restore', ['--discard' => true])
            ->assertSuccessful();
    }

    public function test_h10_duplicate_keys_in_env_last_value_wins_on_curate(): void
    {
        $this->seedEnvWithMarker('local', implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="dup-jwt"',
            'DB_DATABASE="first_db"',
            'DB_DATABASE="second_db"',
        ]));

        $this->artisan('env:curate', ['--no-interaction' => true])
            ->assertSuccessful();

        $this->assertStringContainsString('DB_DATABASE="second_db"', File::get($this->profiles->envPath()));
        $this->assertStringNotContainsString('first_db', File::get($this->profiles->envPath()));
    }

    public function test_h11_orphan_keys_preserved_in_extra_section(): void
    {
        $this->seedEnvWithMarker('local', implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="orphan-jwt"',
            'ORPHAN_PROBE_KEY="x"',
        ]));

        $this->artisan('env:curate', ['--no-interaction' => true])
            ->assertSuccessful();

        $env = File::get($this->profiles->envPath());
        $this->assertStringContainsString('# --- Extra keys (not in template) ---', $env);
        $this->assertStringContainsString('ORPHAN_PROBE_KEY="x"', $env);
    }

    public function test_h12_force_past_backup_allows_second_operation_leaving_stale_pending(): void
    {
        $envPath = $this->profiles->envPath();
        File::put($envPath, "APP_ENV=\"local\"\n");
        $first = $this->backups->createPending('stale-first', [$envPath => '.env']);

        $this->seedEnvWithMarker('local', implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="past-backup-jwt"',
        ]));

        $this->artisan('env:curate', ['--force-past-backup' => true, '--no-interaction' => true])
            ->assertSuccessful();

        // First stale pending remains; successful curate commits its own backup.
        $this->assertNotNull($this->backups->findById($first->id));
        $this->assertTrue($this->backups->hasPending());
    }

    // --- Tier 3: Insane ---

    public function test_i01_curate_empty_env_applies_template_defaults(): void
    {
        $this->seedEnvWithMarker('local', '');
        File::put($this->profiles->envPath(), '');

        $result = $this->curator->curate(dryRun: true);

        $this->assertNotEmpty($result['added_keys']);
    }

    public function test_i02_curate_without_profile_context_throws(): void
    {
        File::put($this->profiles->envPath(), "FOO=\"bar\"\n");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot determine active profile');

        $this->curator->curate(dryRun: true);
    }

    public function test_i04_crlf_env_curated_to_lf(): void
    {
        $this->seedEnvWithMarker('local', "APP_ENV=\"local\"\r\nDB_DATABASE=\"crlf_db\"\r\n");
        File::put($this->profiles->envPath(), implode("\r\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="crlf-jwt"',
            'DB_DATABASE="crlf_db"',
        ]));

        $this->artisan('env:curate', ['--no-interaction' => true])
            ->assertSuccessful();

        // Template lines may retain CRLF; values must survive curation.
        $this->assertStringContainsString('DB_DATABASE="crlf_db"', File::get($this->profiles->envPath()));
    }

    public function test_i11_pending_dir_without_meta_is_ignored(): void
    {
        $orphan = $this->backups->pendingRoot().DIRECTORY_SEPARATOR.'orphan-no-meta';
        $filesDir = $orphan.DIRECTORY_SEPARATOR.'files';
        File::makeDirectory($filesDir, recursive: true);
        File::put($filesDir.DIRECTORY_SEPARATOR.'.env', "X=1\n");

        $this->assertFalse($this->backups->hasPending());
    }

    public function test_i12_multiple_pending_restore_targets_latest(): void
    {
        $envPath = $this->profiles->envPath();
        File::put($envPath, "VERSION=\"1\"\n");
        $this->backups->createPending('v1', [$envPath => '.env']);

        sleep(1);

        File::put($envPath, "VERSION=\"2\"\n");
        $second = $this->backups->createPending('v2', [$envPath => '.env']);
        File::put($envPath, "VERSION=\"mutated\"\n");

        $this->artisan('env:restore')
            ->assertSuccessful();

        $this->assertStringContainsString('VERSION="2"', File::get($envPath));
        $this->assertNotNull($this->backups->findById($second->id));
    }

    public function test_i13_restore_unknown_id_reports_no_pending(): void
    {
        $this->artisan('env:restore', ['id' => 'does-not-exist-000'])
            ->expectsOutputToContain('No pending')
            ->assertSuccessful();
    }

    public function test_i15_key_generate_without_env_skips_backup(): void
    {
        $writer = new EnvironmentKeyWriter;

        $this->expectException(\Illuminate\Contracts\Filesystem\FileNotFoundException::class);

        $this->guard->run(
            'key:generate',
            fn (): string => $writer->writeAppKey($this->profiles->envPath()),
            [],
        );
    }

    public function test_i16_edited_env_not_in_snapshot_until_switch_away(): void
    {
        $this->seedEnvWithMarker('local', implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="stale-snap-jwt"',
            'DB_DATABASE="snapshot_original"',
        ]));
        $this->profiles->saveSnapshot('local');

        File::put($this->profiles->envPath(), str_replace(
            'snapshot_original',
            'edited_active_only',
            File::get($this->profiles->envPath()),
        ));

        $this->assertStringContainsString('snapshot_original', File::get($this->profiles->profilePath('local')));
        $this->assertStringContainsString('edited_active_only', File::get($this->profiles->envPath()));
    }

    public function test_i17_curate_sync_snapshot_when_unchanged_still_syncs(): void
    {
        $this->seedEnvWithMarker('local', File::get(base_path('.env.local.example')));
        $this->profiles->saveSnapshot('local');
        File::put($this->profiles->profilePath('local'), "STALE_SNAPSHOT=true\n");

        $this->artisan('env:curate', ['--sync-snapshot' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertStringNotContainsString('STALE_SNAPSHOT', File::get($this->profiles->profilePath('local')));
        $this->assertSame(
            File::get($this->profiles->envPath()),
            File::get($this->profiles->profilePath('local')),
        );
    }

    public function test_i18_install_force_without_snapshot_only_backups_env(): void
    {
        $this->seedEnvWithMarker('local', implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="no-snap-jwt"',
            'DB_DATABASE="only_env_backup"',
        ]));

        $this->assertFalse($this->profiles->snapshotExists('local'));

        $this->artisan('env:install', [
            'profile' => 'local',
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertFileExists($this->profiles->profilePath('local'));
    }

    public function test_i19_very_long_value_survives_backup_and_curate(): void
    {
        $long = str_repeat('x', 65536);
        $this->seedEnvWithMarker('local', implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="long-jwt"',
            'HCAPTCHA_SECRET="'.$long.'"',
        ]));

        $this->artisan('env:curate', ['--no-interaction' => true])
            ->assertSuccessful();

        $this->assertStringContainsString($long, File::get($this->profiles->envPath()));
    }

    public function test_i21_round_trip_curate_switch_preserves_orphans_and_secrets(): void
    {
        $this->seedEnvWithMarker('local', implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'APP_PREVIOUS_KEYS=""',
            'JWT_SECRET="roundtrip-jwt-secret"',
            'ARCHIVD_UPLOAD_PROXY_URL="http://roundtrip.test"',
            'DB_DATABASE="roundtrip_local"',
        ]));
        $this->profiles->saveSnapshot('local');

        $this->artisan('env:curate', ['--sync-snapshot' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->artisan('env:switch', ['profile' => 'staging', '--no-interaction' => true])
            ->assertSuccessful();

        File::put($this->profiles->envPath(), str_replace(
            'monolith_staging',
            'roundtrip_staging',
            File::get($this->profiles->envPath()),
        ));

        $this->artisan('env:switch', ['profile' => 'local', '--no-interaction' => true])
            ->assertSuccessful();

        $env = File::get($this->profiles->envPath());
        $this->assertStringContainsString('roundtrip-jwt-secret', $env);
        $this->assertStringContainsString('ARCHIVD_UPLOAD_PROXY_URL="http://roundtrip.test"', $env);
        $this->assertStringContainsString('roundtrip_local', $env);
    }

    public function test_i22_backup_create_then_install_blocked(): void
    {
        $this->seedEnv(['APP_ENV="local"', 'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"', 'APP_PREVIOUS_KEYS=""']);

        $this->artisan('env:backup:create')->assertSuccessful();

        $this->artisan('env:install', ['profile' => 'local', '--force' => true, '--no-interaction' => true])
            ->assertFailed();
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function seedEnv(array $lines): void
    {
        File::put($this->profiles->envPath(), implode("\n", $lines)."\n");
    }

    /**
     * @param  array<int, string>|string  $content
     */
    private function seedEnvWithMarker(string $profile, array|string $content): void
    {
        if (is_array($content)) {
            $content = implode("\n", $content)."\n";
        }

        File::put($this->profiles->envPath(), $content);
        File::put($this->profiles->markerPath(), $profile.PHP_EOL);
    }
}
