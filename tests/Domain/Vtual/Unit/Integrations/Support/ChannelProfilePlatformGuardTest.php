<?php

namespace Tests\Domain\Vtual\Unit\Integrations\Support;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Support\ChannelProfilePlatformGuard;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChannelProfilePlatformGuardTest extends TestCase
{
    #[Test]
    public function detect_host_returns_youtube_for_youtube_urls(): void
    {
        $this->assertSame(PlatformKey::Youtube, ChannelProfilePlatformGuard::detectHost('https://www.youtube.com/@AizawaEma'));
        $this->assertSame(PlatformKey::Youtube, ChannelProfilePlatformGuard::detectHost('youtube.com/@handle'));
        $this->assertSame(PlatformKey::Youtube, ChannelProfilePlatformGuard::detectHost('https://youtu.be/abc123'));
    }

    #[Test]
    public function detect_host_returns_twitch_for_twitch_urls(): void
    {
        $this->assertSame(PlatformKey::Twitch, ChannelProfilePlatformGuard::detectHost('https://www.twitch.tv/ludwig'));
        $this->assertSame(PlatformKey::Twitch, ChannelProfilePlatformGuard::detectHost('twitch.tv/login'));
        $this->assertSame(PlatformKey::Twitch, ChannelProfilePlatformGuard::detectHost('https://m.twitch.tv/login'));
    }

    #[Test]
    public function detect_host_returns_null_for_bare_handles(): void
    {
        $this->assertNull(ChannelProfilePlatformGuard::detectHost('@AizawaEma'));
        $this->assertNull(ChannelProfilePlatformGuard::detectHost('ludwig'));
        $this->assertNull(ChannelProfilePlatformGuard::detectHost('UCPkKpOHxEDcwmUAnRpIu-Ng'));
    }

    #[Test]
    public function assert_matches_platform_rejects_youtube_url_with_twitch_selected(): void
    {
        $this->expectException(ValidationException::class);

        try {
            ChannelProfilePlatformGuard::assertMatchesPlatform(
                PlatformKey::Twitch,
                'https://www.youtube.com/@AizawaEma',
            );
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('profile', $e->errors());

            throw $e;
        }
    }

    #[Test]
    public function assert_matches_platform_rejects_twitch_url_with_youtube_selected(): void
    {
        $this->expectException(ValidationException::class);

        try {
            ChannelProfilePlatformGuard::assertMatchesPlatform(
                PlatformKey::Youtube,
                'https://www.twitch.tv/ludwig',
            );
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('profile', $e->errors());

            throw $e;
        }
    }

    #[Test]
    public function assert_matches_platform_allows_bare_handle_for_either_platform(): void
    {
        ChannelProfilePlatformGuard::assertMatchesPlatform(PlatformKey::Youtube, '@stream');
        ChannelProfilePlatformGuard::assertMatchesPlatform(PlatformKey::Twitch, '@stream');

        $this->assertTrue(true);
    }
}
