<?php

namespace Tests\Unit\Support\Settings;

use App\Support\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsServicePresentGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_present_group_includes_options_for_notification_delivery(): void
    {
        $presented = app(SettingsService::class)->presentGroup('platform');

        $this->assertArrayHasKey('platform.notifications.delivery', $presented);
        $this->assertSame(
            ['polling', 'realtime'],
            $presented['platform.notifications.delivery']['options'] ?? null,
        );
        $this->assertSame('string', $presented['platform.notifications.delivery']['type']);
    }

    public function test_present_group_omits_options_for_plain_string_keys(): void
    {
        $presented = app(SettingsService::class)->presentGroup('platform');

        $this->assertArrayHasKey('platform.auth.default_role', $presented);
        $this->assertArrayNotHasKey('options', $presented['platform.auth.default_role']);
    }
}
