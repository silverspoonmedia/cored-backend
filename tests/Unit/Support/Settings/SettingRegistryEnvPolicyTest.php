<?php

namespace Tests\Unit\Support\Settings;

use App\Support\EnvironmentVariableCatalog;
use App\Support\Settings\SettingRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the env-vs-DB policy: operational SettingRegistry keys must not
 * remain in EnvironmentVariableCatalog (Tier B lives in system_settings).
 */
class SettingRegistryEnvPolicyTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Env keys removed when operational settings moved to system_settings + config literals.
     *
     * @return list<string>
     */
    private function removedOperationalEnvKeys(): array
    {
        return [
            'LOCALE_COOKIE',
            'LOCALE_COOKIE_MINUTES',
            'JWT_TTL',
            'JWT_REFRESH_TTL',
            'JWT_REMEMBER_TTL',
            'JWT_REFRESH_SKEW',
            'JWT_BLACKLIST_GRACE_PERIOD',
            'AUTH_DEFAULT_ROLE',
            'AUTH_VERIFICATION_EXPIRE',
            'TWO_FACTOR_WINDOW',
            'TWO_FACTOR_ISSUER',
            'ERRORLOG_ENABLED',
            'ERRORLOG_CLEAN_AFTER_DAYS',
            'HCAPTCHA_ENABLED',
            'APP_NOTIFICATION_TYPE',
            'APP_NOTIFICATION_POLLING_INTERVAL',
            'PAYMENT_PREFER_CHANNEL_CHECKOUT',
            'PAYMENT_CHANNEL_FEE_BEARER',
            'PAYMENT_REPORTING_TIMEZONE',
            'FRANKFURTER_CACHE_TTL',
            'archivd_DISABLED_REGIONS',
            'archivd_CHUNK_SIZE',
            'archivd_MULTIPART_THRESHOLD',
            'archivd_MAX_CONCURRENT_PARTS',
            'archivd_DOWNLOAD_VAULT_ENABLED',
            'INFERENCE_DEFAULT_PROVIDER_SLUG',
            'MICROSTOCK_DAILY_FREE_ENABLED',
            'MAIL_FROM_ADDRESS',
            'MAIL_FROM_NAME',
            'PAYMENT_DEFAULT_BUYER_PHONE',
            'FRANKFURTER_TIMEOUT',
            'MICROSTOCK_AI_VISION_MODEL',
            'MICROSTOCK_AI_VISION_MODEL_FREE',
            'MICROSTOCK_AI_VISION_TIMEOUT',
            'MICROSTOCK_AI_THINKING_MODEL',
            'MICROSTOCK_AI_THINKING_MODEL_FREE',
            'MICROSTOCK_AI_THINKING_TIMEOUT',
            'MICROSTOCK_AI_VISION_VARIANT',
            'MICROSTOCK_AI_UPSTREAM_WINDOW_SECONDS',
            'MICROSTOCK_AI_UPSTREAM_TOKEN_BUDGET',
            'MICROSTOCK_AI_INFERENCE_PROVIDER',
            'MICROSTOCK_AI_VISION_ENDPOINT',
            'MICROSTOCK_AI_VISION_API_KEY',
            'MICROSTOCK_AI_THINKING_ENDPOINT',
            'MICROSTOCK_AI_THINKING_API_KEY',
            'VTUAL_AI_CLASSIFY_ENDPOINT',
            'VTUAL_AI_CLASSIFY_API_KEY',
            'VTUAL_YT_PROBE_CHANNEL_ID',
            'VTUAL_CLAIM_TOKEN_PREFIX',
            'VTUAL_CLAIM_TOKEN_BYTES',
            'VTUAL_CHANNEL_CLAIM_TOKEN_PREFIX',
            'VTUAL_CHANNEL_CLAIM_TOKEN_BYTES',
        ];
    }

    public function test_operational_env_keys_are_not_in_environment_catalog(): void
    {
        $catalog = array_keys(EnvironmentVariableCatalog::types());

        foreach ($this->removedOperationalEnvKeys() as $key) {
            $this->assertNotContains(
                $key,
                $catalog,
                "Operational env key [{$key}] must not remain in EnvironmentVariableCatalog."
            );
        }
    }

    public function test_config_path_index_covers_every_registry_entry(): void
    {
        $index = SettingRegistry::configPathIndex();

        foreach (SettingRegistry::definitions() as $registryKey => $definition) {
            $this->assertArrayHasKey(
                $definition['config'],
                $index,
                "Missing config path index for registry key [{$registryKey}]."
            );
            $this->assertSame($registryKey, $index[$definition['config']]);
        }
    }

    public function test_registry_config_paths_resolve_via_setting_config(): void
    {
        $this->assertNotNull(setting_config('jwt.ttl'));
        $this->assertNotNull(setting_config('archivd.max_file_size_bytes'));
        $this->assertNotNull(setting_config('microstock.daily_free.enabled'));
        $this->assertNotNull(setting_config('vtual.claim.ttl_hours'));
    }

    public function test_operational_env_keys_are_not_used_in_config_files(): void
    {
        $files = glob(config_path('*.php')) ?: [];

        foreach ($this->removedOperationalEnvKeys() as $key) {
            foreach ($files as $file) {
                $content = (string) file_get_contents($file);

                $this->assertDoesNotMatchRegularExpression(
                    "/env\s*\(\s*['\"]".preg_quote($key, '/')."['\"]/",
                    $content,
                    "Operational env key [{$key}] must not remain in ".basename($file).'.'
                );
            }
        }
    }
}
