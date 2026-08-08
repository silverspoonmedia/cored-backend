<?php

namespace Tests\Domain\Vtual\Unit\Integrations;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Support\ChannelProfileInputKind;
use App\Domain\Vtual\Integrations\Support\ChannelProfileInputParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChannelProfileInputParserTest extends TestCase
{
    private const YT_ID = 'UCPkKpOHxEDcwmUAnRpIu-Ng';

    #[Test]
    #[DataProvider('youtubeCases')]
    public function parses_youtube(string $raw, ChannelProfileInputKind $kind, string $value): void
    {
        $parsed = ChannelProfileInputParser::parse(PlatformKey::Youtube, $raw);
        $this->assertSame($kind, $parsed->kind);
        $this->assertSame($value, $parsed->value);
    }

    /**
     * @return array<string, array{0: string, 1: ChannelProfileInputKind, 2: string}>
     */
    public static function youtubeCases(): array
    {
        $id = self::YT_ID;

        return [
            'bare_uc' => [$id, ChannelProfileInputKind::ChannelId, $id],
            'channel_url' => ['https://www.youtube.com/channel/'.$id, ChannelProfileInputKind::ChannelId, $id],
            'at_handle' => ['@aizawaema', ChannelProfileInputKind::Handle, 'aizawaema'],
            'handle_url' => ['https://www.youtube.com/@aizawaema', ChannelProfileInputKind::Handle, 'aizawaema'],
            'c_url' => ['https://youtube.com/c/SomeName', ChannelProfileInputKind::Handle, 'SomeName'],
            'bare_handle' => ['aizawaema', ChannelProfileInputKind::Handle, 'aizawaema'],
            'encoded_handle_url' => [
                'https://www.youtube.com/@%E5%85%AB%E9%9B%B2%E3%81%B9%E3%81%AB',
                ChannelProfileInputKind::Handle,
                '八雲べに',
            ],
        ];
    }

    #[Test]
    #[DataProvider('twitchCases')]
    public function parses_twitch(string $raw, ChannelProfileInputKind $kind, string $value): void
    {
        $parsed = ChannelProfileInputParser::parse(PlatformKey::Twitch, $raw);
        $this->assertSame($kind, $parsed->kind);
        $this->assertSame($value, $parsed->value);
    }

    /**
     * @return array<string, array{0: string, 1: ChannelProfileInputKind, 2: string}>
     */
    public static function twitchCases(): array
    {
        return [
            'numeric_id' => ['123456789', ChannelProfileInputKind::ChannelId, '123456789'],
            'login' => ['ludwig', ChannelProfileInputKind::Handle, 'ludwig'],
            'login_at' => ['@Ludwig', ChannelProfileInputKind::Handle, 'ludwig'],
            'url' => ['https://www.twitch.tv/ludwig', ChannelProfileInputKind::Handle, 'ludwig'],
            'url_no_scheme' => ['twitch.tv/shroud', ChannelProfileInputKind::Handle, 'shroud'],
        ];
    }

    #[Test]
    public function empty_throws(): void
    {
        $this->expectException(VtualException::class);
        ChannelProfileInputParser::parse(PlatformKey::Youtube, '   ');
    }

    #[Test]
    public function kick_throws(): void
    {
        $this->expectException(VtualException::class);
        ChannelProfileInputParser::parse(PlatformKey::Kick, 'someone');
    }

    #[Test]
    public function short_uc_treated_as_handle_not_id(): void
    {
        // Too short to be UC id → handle path
        $parsed = ChannelProfileInputParser::parse(PlatformKey::Youtube, 'UC123');
        $this->assertSame(ChannelProfileInputKind::Handle, $parsed->kind);
        $this->assertSame('UC123', $parsed->value);
    }
}
