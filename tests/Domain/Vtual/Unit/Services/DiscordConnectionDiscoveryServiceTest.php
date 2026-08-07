<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelDiscoverySource;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Jobs\RunChannelInitialContentBackfillJob;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\DiscordConnectionDiscoveryService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DiscordConnectionDiscoveryServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private CreatorService $creators;

    private DiscordConnectionDiscoveryService $discovery;

    private Platform $youtube;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creators = app(CreatorService::class);
        $this->discovery = app(DiscordConnectionDiscoveryService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    #[Test]
    public function apply_binds_private_channels_and_sets_contract_from_profile_match(): void
    {
        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Discovery Orphan']);
        $publicChannel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $orphan->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-PUBLIC-1',
            'handle' => 'public-1',
            'display_name' => 'Public One',
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Public,
        ]);

        $connections = [
            ['type' => 'youtube', 'id' => 'UC-PUBLIC-1', 'name' => 'Public One', 'verified' => true],
            ['type' => 'youtube', 'id' => 'UC-PRIVATE-2', 'name' => 'Private Two', 'verified' => true],
        ];

        $result = $this->discovery->apply($orphan, $connections);

        $this->assertSame($publicChannel->id, $result->contractChannel?->id);
        $this->assertCount(1, $result->boundPrivate);

        $private = Channel::query()
            ->where('creator_id', $orphan->id)
            ->where('external_id', 'UC-PRIVATE-2')
            ->first();
        $this->assertNotNull($private);
        $this->assertSame(ChannelVisibility::Private, $private->visibility);
        $this->assertSame(ChannelDiscoverySource::DiscordConnection, $private->discovery_source);
        $this->assertNull($private->content_initial_backfill_at);
    }

    #[Test]
    public function apply_closes_curation_gap_when_only_discord_channel_exists(): void
    {
        Bus::fake([RunChannelInitialContentBackfillJob::class]);

        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Gap Orphan']);
        Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $orphan->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-WRONG',
            'handle' => 'wrong',
            'display_name' => 'Wrong Channel',
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Public,
        ]);

        $connections = [
            ['type' => 'youtube', 'id' => 'UC-REAL', 'name' => 'Real Channel', 'verified' => true],
        ];

        $result = $this->discovery->apply($orphan, $connections);

        $this->assertNotNull($result->contractChannel);
        $this->assertSame('UC-REAL', $result->contractChannel->external_id);
        $this->assertSame(ChannelVisibility::Private, $result->contractChannel->visibility);
        Bus::assertNothingDispatched();
    }

    #[Test]
    public function apply_upserts_external_links_from_non_yt_connections(): void
    {
        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Links Orphan']);

        $connections = [
            ['type' => 'github', 'id' => 'gh-1', 'name' => 'octocat', 'verified' => true],
        ];

        $result = $this->discovery->apply($orphan, $connections);

        $this->assertSame(1, $result->externalLinksUpserted);
        $this->assertDatabaseHas('vtual_external_links', [
            'linkable_type' => Creator::class,
            'linkable_id' => $orphan->id,
            'url' => 'https://github.com/octocat',
        ]);
    }
}
