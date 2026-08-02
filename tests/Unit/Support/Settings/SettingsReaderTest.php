<?php

namespace Tests\Unit\Support\Settings;

use App\Support\Settings\Models\SystemSetting;
use App\Support\Settings\Repositories\Eloquent\SystemSettingRepository;
use App\Support\Settings\SettingsReader;
use App\Support\Settings\SettingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_override_wins_over_config_fallback(): void
    {
        SystemSetting::query()->create([
            'key' => 'platform.jwt.ttl',
            'group' => 'platform',
            'type' => SettingType::Int->value,
            'value' => 120,
        ]);

        app(SystemSettingRepository::class)->flushCache();

        $reader = app(SettingsReader::class);

        $this->assertSame(120, $reader->get('platform.jwt.ttl'));
    }

    public function test_config_fallback_when_no_db_row(): void
    {
        $reader = app(SettingsReader::class);

        $this->assertSame(
            (int) config('jwt.ttl'),
            $reader->get('platform.jwt.ttl'),
        );
    }

    public function test_setting_config_resolves_by_config_path(): void
    {
        SystemSetting::query()->create([
            'key' => 'platform.hcaptcha.enabled',
            'group' => 'platform',
            'type' => SettingType::Bool->value,
            'value' => true,
        ]);

        app(SystemSettingRepository::class)->flushCache();

        $this->assertTrue(setting_config('hcaptcha.enabled'));
    }
}
