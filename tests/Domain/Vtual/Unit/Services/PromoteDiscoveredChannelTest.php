<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelDiscoverySource;
use App\Domain\Vtual\Enums\ChannelFetchPoolStatus;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelFetchPoolItem;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelService;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Isolated coverage for ChannelService::promoteDiscoveredChannel — the only
 * path that flips a Discord-discovered private channel to public. Each guard
 * gets its own test so a regression names the rule that broke.
 */
class PromoteDiscoveredChannelTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ChannelService $channels;

    private CreatorService $creators;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->channels = app(ChannelService::class);
        $this->creators = app(CreatorService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        Bus::fake();
    }

    #[Test]
    public function owner_promotes_discovered_channel_and_enqueues_hydrate(): void
    {
        $owner = $this->userWithRole('User');
        $creator = $this->creators->create($owner, ['display_name' => 'Discovered Fox']);
        $channel = $this->discoveredChannel($creator);

        $promoted = $this->channels->promoteDiscoveredChannel($owner, (string) $channel->id);

        $this->assertSame(ChannelVisibility::Public, $promoted->visibility);
        $this->assertSame(ChannelVisibility::Public, $channel->refresh()->visibility);

        $this->assertSame(1, ChannelFetchPoolItem::query()
            ->where('platform_id', $this->youtube->id)
            ->where('external_id', $channel->external_id)
            ->where('status', ChannelFetchPoolStatus::Pending->value)
            ->count());
    }

    #[Test]
    public function staff_bypass_promotes_channel_of_any_creator(): void
    {
        $admin = $this->userWithRole('Admin');
        $owner = $this->userWithRole('User');
        $creator = $this->creators->create($owner, ['display_name' => 'Other Fox']);
        $channel = $this->discoveredChannel($creator);

        $promoted = $this->channels->promoteDiscoveredChannel($admin, (string) $channel->id);

        $this->assertSame(ChannelVisibility::Public, $promoted->visibility);
    }

    #[Test]
    public function non_member_cannot_promote(): void
    {
        $owner = $this->userWithRole('User');
        $outsider = $this->userWithRole('User');
        $creator = $this->creators->create($owner, ['display_name' => 'Guarded Fox']);
        $channel = $this->discoveredChannel($creator);

        try {
            $this->channels->promoteDiscoveredChannel($outsider, (string) $channel->id);
            $this->fail('Expected VtualException for a non-member actor.');
        } catch (VtualException $e) {
            $this->assertSame(403, $e->statusCode());
        }

        $this->assertSame(ChannelVisibility::Private, $channel->refresh()->visibility);
    }

    #[Test]
    public function already_public_channel_is_rejected(): void
    {
        $owner = $this->userWithRole('User');
        $creator = $this->creators->create($owner, ['display_name' => 'Public Fox']);
        $channel = $this->discoveredChannel($creator, visibility: ChannelVisibility::Public);

        $this->assertRejected($owner, $channel, 'not_private_discovered');
        $this->assertSame(ChannelVisibility::Public, $channel->refresh()->visibility);
    }

    #[Test]
    public function channel_not_discovered_via_discord_is_rejected(): void
    {
        $owner = $this->userWithRole('User');
        $creator = $this->creators->create($owner, ['display_name' => 'Claimed Fox']);
        $channel = $this->discoveredChannel($creator, source: ChannelDiscoverySource::ChannelClaim);

        $this->assertRejected($owner, $channel, 'promote_not_discovered');
        $this->assertSame(ChannelVisibility::Private, $channel->refresh()->visibility);
    }

    #[Test]
    public function archived_channel_is_rejected(): void
    {
        $owner = $this->userWithRole('User');
        $creator = $this->creators->create($owner, ['display_name' => 'Archived Fox']);
        $channel = $this->discoveredChannel($creator, status: ChannelStatus::Archived);

        $this->assertRejected($owner, $channel, 'archived');
        $this->assertSame(ChannelStatus::Archived, $channel->refresh()->status);
    }

    #[Test]
    public function disabled_platform_is_rejected(): void
    {
        $owner = $this->userWithRole('User');
        $creator = $this->creators->create($owner, ['display_name' => 'Disabled Platform Fox']);

        $disabled = Platform::query()->updateOrCreate(
            ['key' => 'twitch'],
            [
                'name' => 'Twitch',
                'enabled' => false,
                'supports_live' => true,
                'supports_vod' => true,
            ],
        );
        $channel = $this->discoveredChannel($creator, platform: $disabled);

        $this->assertRejected($owner, $channel, 'platform_disabled');
        $this->assertSame(ChannelVisibility::Private, $channel->refresh()->visibility);
    }

    #[Test]
    public function kick_channel_is_rejected_even_when_platform_enabled(): void
    {
        $owner = $this->userWithRole('User');
        $creator = $this->creators->create($owner, ['display_name' => 'Kick Fox']);

        $kick = Platform::query()->updateOrCreate(
            ['key' => 'kick'],
            [
                'name' => 'Kick',
                'enabled' => true,
                'supports_live' => true,
                'supports_vod' => true,
            ],
        );
        $channel = $this->discoveredChannel($creator, platform: $kick);

        $this->assertRejected($owner, $channel, 'promote_unsupported_platform');
        $this->assertSame(ChannelVisibility::Private, $channel->refresh()->visibility);
    }

    private function assertRejected(User $actor, Channel $channel, string $key): void
    {
        try {
            $this->channels->promoteDiscoveredChannel($actor, (string) $channel->id);
            $this->fail('Expected VtualException for '.$key);
        } catch (VtualException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertSame(__('api/vtual.channel.'.$key), $e->getMessage());
        }

        $this->assertSame(0, ChannelFetchPoolItem::query()
            ->where('external_id', $channel->external_id)
            ->count());
    }

    private function discoveredChannel(
        Creator $creator,
        ChannelVisibility $visibility = ChannelVisibility::Private,
        ChannelDiscoverySource $source = ChannelDiscoverySource::DiscordConnection,
        ChannelStatus $status = ChannelStatus::Active,
        ?Platform $platform = null,
    ): Channel {
        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => ($platform ?? $this->youtube)->id,
            'external_id' => 'UC'.Str::lower(Str::random(22)),
            'handle' => 'discovered-'.Str::lower(Str::random(6)),
            'display_name' => $creator->display_name,
            'status' => $status,
            'visibility' => $visibility,
            'discovery_source' => $source,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Promote',
            'email' => strtolower($role).'-promote-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
