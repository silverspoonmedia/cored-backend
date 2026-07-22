<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\CreatorClaimClosedReason;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\ExternalLinkKind;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelClaim;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelService;
use App\Domain\Vtual\Services\CreatorMergeService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\ExternalLinkService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CreatorMergeServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private CreatorMergeService $merge;

    private CreatorService $creators;

    private ChannelService $channels;

    private ExternalLinkService $links;

    private Platform $youtube;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->merge = app(CreatorMergeService::class);
        $this->creators = app(CreatorService::class);
        $this->channels = app(ChannelService::class);
        $this->links = app(ExternalLinkService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
    }

    #[Test]
    public function owner_merge_moves_channels_and_links_archives_source(): void
    {
        $user = $this->userWithRole('User');
        $source = $this->creators->create($user, ['display_name' => 'Asumi Sena', 'slug' => 'asumi-sena']);
        $dest = $this->creators->create($user, ['display_name' => 'Aizawa Ema', 'slug' => 'aizawa-ema']);

        $channel = $this->activateChannel($source->id, 'UC-SOURCE-1', 'asumi');
        $link = $this->links->create($user, $source->id, [
            'label' => 'Twitter',
            'url' => 'https://x.com/asumi',
            'kind' => ExternalLinkKind::Social->value,
        ]);

        // Pending channel claim on source must be cancelled.
        ChannelClaim::query()->create([
            'id' => (string) Str::uuid7(),
            'user_id' => $user->id,
            'creator_id' => $source->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-PENDING',
            'profile_input' => '@pending',
            'token' => 'vtual-ch-test',
            'status' => CreatorClaimStatus::Pending,
            'attempt_count' => 0,
            'expires_at' => now()->addDay(),
        ]);

        $result = $this->merge->merge($user, $source->id, $dest->id);

        $this->assertSame((string) $dest->id, (string) $result->id);
        $this->assertSame(EntityStatus::Active, $result->status);

        $channel->refresh();
        $this->assertSame((string) $dest->id, (string) $channel->creator_id);

        $link->refresh();
        $this->assertSame((string) $dest->id, (string) $link->linkable_id);

        $source->refresh();
        $this->assertSame(EntityStatus::Archived, $source->status);
        $this->assertStringStartsWith('merged-', (string) $source->slug);
        $this->assertSame(0, CreatorMember::query()->where('creator_id', $source->id)->count());
        $this->assertFalse($this->creators->isClaimable($source));

        $this->assertSame(
            CreatorClaimStatus::Cancelled,
            ChannelClaim::query()->where('external_id', 'UC-PENDING')->first()?->status,
        );
        $this->assertSame(
            CreatorClaimClosedReason::Merged,
            ChannelClaim::query()->where('external_id', 'UC-PENDING')->first()?->closed_reason,
        );
    }

    #[Test]
    public function channel_external_id_clash_rejects_without_partial_move(): void
    {
        // Production unique (platform_id, external_id) blocks this state; preflight still
        // guards races / future schema. Drop unique for this test only.
        $user = $this->userWithRole('User');
        $source = $this->creators->create($user, ['display_name' => 'A']);
        $dest = $this->creators->create($user, ['display_name' => 'B']);

        $srcCh = $this->activateChannel($source->id, 'UC-SRC-CLASH', 'a');
        $dstCh = $this->activateChannel($dest->id, 'UC-DST-CLASH', 'b');

        \Illuminate\Support\Facades\Schema::table('vtual_channels', function ($table): void {
            $table->dropUnique(['platform_id', 'external_id']);
        });

        Channel::query()->whereKey($dstCh->id)->update(['external_id' => 'UC-SRC-CLASH']);

        try {
            $this->merge->merge($user, $source->id, $dest->id);
            $this->fail('Expected VtualException');
        } catch (VtualException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertStringContainsString('UC-SRC-CLASH', $e->getMessage());
        }

        $this->assertSame(1, Channel::query()->where('creator_id', $source->id)->count());
        $this->assertSame(EntityStatus::Active, $source->fresh()?->status);
        $this->assertSame((string) $source->id, (string) $srcCh->fresh()?->creator_id);
    }

    #[Test]
    public function non_owner_cannot_merge(): void
    {
        $owner = $this->userWithRole('User');
        $peer = $this->userWithRole('User');
        $source = $this->creators->create($owner, ['display_name' => 'A']);
        $dest = $this->creators->create($owner, ['display_name' => 'B']);

        $this->expectException(VtualException::class);
        $this->merge->merge($peer, $source->id, $dest->id);
    }

    #[Test]
    public function staff_can_merge_orphans(): void
    {
        $a = $this->creators->createOrphan($this->admin, ['display_name' => 'Orphan A']);
        $b = $this->creators->createOrphan($this->admin, ['display_name' => 'Orphan B']);
        $this->activateChannel($a->id, 'UC-ORPHAN', 'orphan');

        $result = $this->merge->merge($this->admin, $a->id, $b->id);

        $this->assertSame((string) $b->id, (string) $result->id);
        $this->assertSame(1, Channel::query()->where('creator_id', $b->id)->count());
        $this->assertSame(EntityStatus::Archived, $a->fresh()?->status);
    }

    #[Test]
    public function self_merge_rejected(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Solo']);

        $this->expectException(VtualException::class);
        $this->merge->merge($user, $creator->id, $creator->id);
    }

    #[Test]
    public function pending_creator_claims_cancelled_with_merged(): void
    {
        $user = $this->userWithRole('User');
        $source = $this->creators->create($user, ['display_name' => 'Src']);
        $dest = $this->creators->create($user, ['display_name' => 'Dst']);
        $channel = $this->activateChannel($source->id, 'UC-PROOF', 'proof');

        CreatorClaim::query()->create([
            'id' => (string) Str::uuid7(),
            'user_id' => $user->id,
            'creator_id' => $source->id,
            'channel_id' => $channel->id,
            'token' => 'vtual-claim-test',
            'status' => CreatorClaimStatus::Pending,
            'attempt_count' => 0,
            'expires_at' => now()->addDay(),
        ]);

        $this->merge->merge($user, $source->id, $dest->id);

        $claim = CreatorClaim::query()->where('token', 'vtual-claim-test')->first();
        $this->assertSame(CreatorClaimStatus::Cancelled, $claim?->status);
        $this->assertSame(CreatorClaimClosedReason::Merged, $claim?->closed_reason);
    }

    private function activateChannel(string $creatorId, string $externalId, string $handle): Channel
    {
        $dto = new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: $externalId,
            handle: $handle,
            displayName: $handle,
            description: 'bio',
            thumbnailUrl: null,
            url: 'https://youtube.com/channel/'.$externalId,
        );

        $channel = $this->channels->activateFromDto($creatorId, $this->youtube, $dto);
        $this->assertSame(ChannelStatus::Active, $channel->status);

        return $channel;
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' User',
            'email' => strtolower($role).'-merge-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
