<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Vtual\Enums\ChannelFetchPoolReason;
use App\Domain\Vtual\Enums\ChannelFetchPoolStatus;
use App\Domain\Vtual\Enums\ContentFetchPoolSource;
use App\Domain\Vtual\Enums\ContentFetchPoolStatus;
use App\Domain\Vtual\Models\ChannelFetchPoolItem;
use App\Domain\Vtual\Models\ContentFetchPoolItem;
use App\Domain\Vtual\Models\Platform;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PruneFetchPoolsCommandTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
    }

    #[Test]
    public function prunes_only_old_done_and_failed_rows(): void
    {
        $oldDone = $this->channelItem(ChannelFetchPoolStatus::Done, now()->subDays(10));
        $oldFailed = $this->channelItem(ChannelFetchPoolStatus::Failed, now()->subDays(10));
        $youngDone = $this->channelItem(ChannelFetchPoolStatus::Done, now()->subDays(2));
        $pending = $this->channelItem(ChannelFetchPoolStatus::Pending, null);
        $processing = $this->channelItem(ChannelFetchPoolStatus::Processing, null);
        $nullProcessedDone = $this->channelItem(ChannelFetchPoolStatus::Done, null);

        $this->artisan('vtual:prune-fetch-pools', ['--days' => 7])->assertSuccessful();

        $this->assertNull(ChannelFetchPoolItem::find($oldDone->id));
        $this->assertNull(ChannelFetchPoolItem::find($oldFailed->id));
        $this->assertNotNull(ChannelFetchPoolItem::find($youngDone->id));
        $this->assertNotNull(ChannelFetchPoolItem::find($pending->id));
        $this->assertNotNull(ChannelFetchPoolItem::find($processing->id));
        $this->assertNotNull(ChannelFetchPoolItem::find($nullProcessedDone->id));
    }

    #[Test]
    public function prunes_content_pool_too(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        \Illuminate\Support\Facades\Bus::fake();

        $user = $this->userWithRole('User');
        $creator = app(\App\Domain\Vtual\Services\CreatorService::class)
            ->create($user, ['display_name' => 'Prune Owner']);
        $channel = app(\App\Domain\Vtual\Services\ChannelService::class)
            ->activateFromDto($creator->id, $this->youtube, new \App\Domain\Vtual\Integrations\Dto\PlatformChannelDto(
                platform: \App\Domain\Vtual\Enums\PlatformKey::Youtube,
                externalId: 'UCPRUNE001',
                handle: 'prune',
                displayName: 'Prune',
                description: null,
                thumbnailUrl: null,
                url: null,
            ));

        $oldDone = $this->contentItem(ContentFetchPoolStatus::Done, now()->subDays(30), (string) $channel->id);
        $youngDone = $this->contentItem(ContentFetchPoolStatus::Done, now(), (string) $channel->id);
        $pending = $this->contentItem(ContentFetchPoolStatus::Pending, null, (string) $channel->id);

        $this->artisan('vtual:prune-fetch-pools')->assertSuccessful();

        $this->assertNull(ContentFetchPoolItem::find($oldDone->id));
        $this->assertNotNull(ContentFetchPoolItem::find($youngDone->id));
        $this->assertNotNull(ContentFetchPoolItem::find($pending->id));
    }

    private function channelItem(ChannelFetchPoolStatus $status, ?\Illuminate\Support\Carbon $processedAt): ChannelFetchPoolItem
    {
        $item = new ChannelFetchPoolItem([
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC'.bin2hex(random_bytes(4)),
            'reason' => ChannelFetchPoolReason::Refresh,
            'status' => $status,
            'requested_at' => now(),
            'processed_at' => $processedAt,
        ]);
        $item->save();

        return $item;
    }

    private function userWithRole(string $role): \App\Domain\Identity\Models\User
    {
        $user = \App\Domain\Identity\Models\User::query()->create([
            'name' => $role.' User',
            'email' => strtolower($role).'-prune-'.uniqid().'@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function contentItem(ContentFetchPoolStatus $status, ?\Illuminate\Support\Carbon $processedAt, string $channelId): ContentFetchPoolItem    {
        $item = new ContentFetchPoolItem([
            'platform_id' => $this->youtube->id,
            'video_external_id' => bin2hex(random_bytes(5)),
            'channel_id' => $channelId,
            'source' => ContentFetchPoolSource::FeedPoll,
            'status' => $status,
            'requested_at' => now(),
            'processed_at' => $processedAt,
        ]);
        $item->save();

        return $item;
    }
}
