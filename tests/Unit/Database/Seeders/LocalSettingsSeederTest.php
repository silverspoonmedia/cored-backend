<?php

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\LocalSettingsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalSettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_local_overrides_when_app_env_is_local(): void
    {
        $this->app['env'] = 'local';

        $this->seed(SettingsSeeder::class);
        $this->assertSame(5, setting('microstock.daily_free.limit_per_user'));

        $this->seed(LocalSettingsSeeder::class);

        $this->assertTrue(setting('microstock.daily_free.enabled'));
        $this->assertSame(1_000_000, setting('microstock.daily_free.limit_per_user'));
        $this->assertSame('realtime', setting('platform.notifications.delivery'));
    }

    public function test_skips_overrides_when_app_env_is_not_local(): void
    {
        $this->assertSame('testing', app()->environment());

        $this->seed(SettingsSeeder::class);
        $this->seed(LocalSettingsSeeder::class);

        $this->assertFalse(setting('microstock.daily_free.enabled'));
        $this->assertSame(5, setting('microstock.daily_free.limit_per_user'));
    }
}
