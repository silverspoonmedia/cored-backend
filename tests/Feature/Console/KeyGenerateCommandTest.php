<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Covers the overridden key:generate command — anchor-based APP_KEY writes and
 * rotation into APP_PREVIOUS_KEYS. Backs up and restores the real .env.
 */
class KeyGenerateCommandTest extends TestCase
{
    private ?string $envBackup = null;

    private bool $hadEnv = false;

    protected function tearDown(): void
    {
        $envPath = base_path('.env');

        if ($this->hadEnv && $this->envBackup !== null) {
            File::put($envPath, $this->envBackup);
        } elseif (File::exists($envPath)) {
            File::delete($envPath);
        }

        parent::tearDown();
    }

    public function test_it_generates_app_key_when_empty_and_leaves_previous_keys_unchanged(): void
    {
        $this->useEnvFile([
            'APP_KEY=""',
            'APP_PREVIOUS_KEYS=""',
        ]);

        $this->artisan('key:generate')
            ->assertSuccessful();

        $env = File::get(base_path('.env'));
        $this->assertMatchesRegularExpression('/^APP_KEY="base64:.+"$/m', $env);
        $this->assertStringContainsString('APP_PREVIOUS_KEYS=""', $env);
    }

    public function test_it_rotates_existing_key_into_previous_keys_with_force(): void
    {
        $oldKey = 'base64:'.base64_encode(random_bytes(32));

        $this->useEnvFile([
            'APP_KEY="'.$oldKey.'"',
            'APP_PREVIOUS_KEYS=""',
        ]);

        $this->artisan('key:generate', ['--force' => true])
            ->assertSuccessful();

        $env = File::get(base_path('.env'));

        $this->assertStringContainsString('APP_PREVIOUS_KEYS="'.$oldKey.'"', $env);
        $this->assertStringNotContainsString('APP_KEY="'.$oldKey.'"', $env);
    }

    public function test_it_works_when_config_app_key_differs_from_env_file(): void
    {
        $this->useEnvFile([
            'APP_KEY=""',
            'APP_PREVIOUS_KEYS=""',
        ]);

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->artisan('key:generate')
            ->assertSuccessful();

        $this->assertMatchesRegularExpression(
            '/^APP_KEY="base64:.+"$/m',
            File::get(base_path('.env')),
        );
    }

    public function test_it_deduplicates_previous_keys_on_repeated_force_rotation(): void
    {
        $firstKey = 'base64:'.base64_encode(random_bytes(32));

        $this->useEnvFile([
            'APP_KEY="'.$firstKey.'"',
            'APP_PREVIOUS_KEYS=""',
        ]);

        $this->artisan('key:generate', ['--force' => true])->assertSuccessful();

        $envAfterFirst = File::get(base_path('.env'));

        preg_match('/^APP_KEY="(.+)"$/m', $envAfterFirst, $matches);
        $secondKey = $matches[1];

        $this->artisan('key:generate', ['--force' => true])->assertSuccessful();

        $env = File::get(base_path('.env'));
        preg_match('/^APP_PREVIOUS_KEYS="(.*)"$/m', $env, $previousMatches);

        $previousKeys = array_filter(explode(',', $previousMatches[1]));

        $this->assertCount(2, $previousKeys);
        $this->assertContains($firstKey, $previousKeys);
        $this->assertContains($secondKey, $previousKeys);

        preg_match('/^APP_KEY="(.+)"$/m', $env, $newMatches);
        $this->assertNotSame($secondKey, $newMatches[1] ?? null);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function useEnvFile(array $lines): void
    {
        $envPath = base_path('.env');
        $this->hadEnv = File::exists($envPath);

        if ($this->hadEnv) {
            $this->envBackup = File::get($envPath);
        }

        File::put($envPath, implode("\n", $lines)."\n");
    }
}
