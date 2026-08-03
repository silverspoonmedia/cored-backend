<?php

namespace Tests\Unit\Support\Settings;

use App\Support\Settings\Models\SystemSetting;
use App\Support\Settings\Repositories\Eloquent\SystemSettingRepository;
use App\Support\Settings\SettingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemSettingRepositoryCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_many_rebuilds_cache_and_same_request_sees_new_values(): void
    {
        $repository = app(SystemSettingRepository::class);

        $this->assertSame((int) config('jwt.ttl'), setting_config('jwt.ttl'));

        $repository->upsertMany(['platform.jwt.ttl' => 999]);

        $this->assertSame(999, setting_config('jwt.ttl'));
    }

    public function test_cache_miss_rebuilds_from_database(): void
    {
        SystemSetting::query()->create([
            'key' => 'platform.jwt.ttl',
            'group' => 'platform',
            'type' => SettingType::Int->value,
            'value' => 77,
        ]);

        Cache::forget('system_settings.snapshot');

        $repository = app(SystemSettingRepository::class);

        $this->assertSame(77, $repository->snapshot()['platform.jwt.ttl']);
        $this->assertTrue(Cache::has('system_settings.snapshot'));
    }

    public function test_rebuild_cache_warms_forever_store(): void
    {
        SystemSetting::query()->create([
            'key' => 'platform.hcaptcha.enabled',
            'group' => 'platform',
            'type' => SettingType::Bool->value,
            'value' => true,
        ]);

        Cache::forget('system_settings.snapshot');

        $snapshot = app(SystemSettingRepository::class)->rebuildCache();

        $this->assertTrue($snapshot['platform.hcaptcha.enabled']);
        $this->assertTrue(Cache::has('system_settings.snapshot'));
    }
}
