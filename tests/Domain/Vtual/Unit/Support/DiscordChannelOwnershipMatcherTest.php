<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Support\DiscordChannelOwnershipMatcher;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordChannelOwnershipMatcherTest extends TestCase
{
    use RefreshDatabase;

    private const YT_ID = 'UCEMMrXP3_WeKiOunPYixMfA';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VtualRoleSeeder::class);
    }

    #[Test]
    public function matches_verified_youtube_connection_by_external_id(): void
    {
        $channel = $this->youtubeChannel(self::YT_ID);

        $this->assertTrue(DiscordChannelOwnershipMatcher::matches($channel, [
            ['type' => 'youtube', 'id' => self::YT_ID, 'name' => 'Neuron', 'verified' => true],
        ]));
    }

    #[Test]
    public function rejects_unverified_connection(): void
    {
        $channel = $this->youtubeChannel(self::YT_ID);

        $this->assertFalse(DiscordChannelOwnershipMatcher::matches($channel, [
            ['type' => 'youtube', 'id' => self::YT_ID, 'name' => 'Neuron', 'verified' => false],
        ]));
    }

    #[Test]
    public function rejects_wrong_platform_type(): void
    {
        $channel = $this->youtubeChannel(self::YT_ID);

        $this->assertFalse(DiscordChannelOwnershipMatcher::matches($channel, [
            ['type' => 'twitch', 'id' => self::YT_ID, 'name' => 'Neuron', 'verified' => true],
        ]));
    }

    #[Test]
    public function matches_any_returns_first_matching_channel(): void
    {
        $channelA = $this->youtubeChannel('UC-AAA');
        $channelB = $this->youtubeChannel('UC-BBB');

        $match = DiscordChannelOwnershipMatcher::firstMatch(
            [$channelA, $channelB],
            [['type' => 'youtube', 'id' => 'UC-BBB', 'name' => 'B', 'verified' => true]],
        );

        $this->assertNotNull($match);
        $this->assertSame('UC-BBB', $match->external_id);
        $this->assertTrue(DiscordChannelOwnershipMatcher::matchesAny(
            [$channelA, $channelB],
            [['type' => 'youtube', 'id' => 'UC-BBB', 'name' => 'B', 'verified' => true]],
        ));
    }

    private function youtubeChannel(string $externalId): Channel
    {
        $platform = Platform::query()->where('key', PlatformKey::Youtube->value)->firstOrFail();
        $creator = Creator::query()->create([
            'slug' => 'matcher-'.uniqid(),
            'display_name' => 'Matcher Creator',
            'status' => 'active',
        ]);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $platform->id,
            'external_id' => $externalId,
            'handle' => 'matcher',
            'display_name' => 'Matcher',
            'status' => ChannelStatus::Active,
        ]);
    }
}
