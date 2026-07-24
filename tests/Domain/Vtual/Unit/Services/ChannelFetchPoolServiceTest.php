<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelFetchPoolReason;
use App\Domain\Vtual\Enums\ChannelFetchPoolStatus;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Models\ChannelFetchPoolItem;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ChannelFetchPoolService;
use App\Domain\Vtual\Services\ChannelService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformCredentialService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ChannelFetchPoolServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ChannelFetchPoolService $pool;

    private ChannelService $channels;

    private CreatorService $creators;

    private PlatformCredentialService $credentials;

    private Platform $youtube;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->pool = app(ChannelFetchPoolService::class);
        $this->channels = app(ChannelService::class);
        $this->creators = app(CreatorService::class);
        $this->credentials = app(PlatformCredentialService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
        config([
            'vtual.channel_pool.threshold' => 50,
            'vtual.channel_pool.max_wait_minutes' => 15,
            'vtual.http.batch.channels' => 50,
        ]);
    }

    #[Test]
    public function enqueue_fifty_unique_ids_flushes_one_http_and_updates_channels(): void
    {
        $this->activateYoutubeCredential();
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Pool Owner']);

        $ids = [];
        for ($i = 1; $i <= 50; $i++) {
            $ids[] = 'UCPOOL'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        }

        foreach ($ids as $id) {
            $this->channels->activateFromDto($creator->id, $this->youtube, new PlatformChannelDto(
                platform: PlatformKey::Youtube,
                externalId: $id,
                handle: 'h-'.$id,
                displayName: 'Old '.$id,
                description: 'old',
                thumbnailUrl: null,
                url: null,
            ));
        }

        $httpCalls = 0;
        Http::fake(function ($request) use (&$httpCalls, $ids) {
            if (str_contains($request->url(), 'youtube/v3/channels')) {
                $httpCalls++;
                $items = [];
                foreach ($ids as $id) {
                    $items[] = [
                        'id' => $id,
                        'snippet' => [
                            'title' => 'New '.$id,
                            'description' => 'new bio',
                            'customUrl' => '@new-'.$id,
                            'publishedAt' => '2017-04-19T14:57:55Z',
                            'thumbnails' => ['high' => ['url' => 'https://img.example/'.$id.'.jpg']],
                        ],
                    ];
                }

                return Http::response(['items' => $items], 200);
            }

            return Http::response([], 404);
        });

        // Enqueue 49 without hitting threshold auto-flush mid-way under test config.
        for ($i = 0; $i < 49; $i++) {
            $this->pool->enqueue($this->youtube, $ids[$i], null, ChannelFetchPoolReason::Manual);
        }
        $this->assertSame(0, $httpCalls);

        // 50th triggers size flush inside enqueue.
        $this->pool->enqueue($this->youtube, $ids[49], null, ChannelFetchPoolReason::Manual);

        $this->assertSame(1, $httpCalls);
        $this->assertSame(50, ChannelFetchPoolItem::query()
            ->where('status', ChannelFetchPoolStatus::Done)
            ->count());
        $this->assertSame(
            'New UCPOOL001',
            \App\Domain\Vtual\Models\Channel::query()->where('external_id', 'UCPOOL001')->value('display_name'),
        );
    }

    #[Test]
    public function age_gate_flushes_without_force_when_oldest_exceeds_max_wait(): void
    {
        $this->activateYoutubeCredential();
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Age']);

        $id = 'UCAGE001';
        $this->channels->activateFromDto($creator->id, $this->youtube, new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: $id,
            handle: 'age',
            displayName: 'Old',
            description: 'old',
            thumbnailUrl: null,
            url: null,
        ));

        $item = $this->pool->enqueue($this->youtube, $id, null, ChannelFetchPoolReason::Manual);
        ChannelFetchPoolItem::query()->whereKey($item->id)->update([
            'requested_at' => now()->subMinutes(16),
        ]);

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [[
                    'id' => $id,
                    'snippet' => [
                        'title' => 'Aged New',
                        'description' => 'bio',
                        'customUrl' => '@age',
                        'publishedAt' => '2017-04-19T14:57:55Z',
                        'thumbnails' => [],
                    ],
                ]],
            ], 200),
        ]);

        $summary = $this->pool->flush(PlatformKey::Youtube, force: false);
        $this->assertSame('flushed', $summary['platforms']['youtube']['status']);
        $this->assertSame(1, $summary['platforms']['youtube']['http']);
        $this->assertSame(
            'Aged New',
            \App\Domain\Vtual\Models\Channel::query()->where('external_id', $id)->value('display_name'),
        );
    }

    #[Test]
    public function young_pending_skips_without_force_processes_with_force(): void
    {
        $this->activateYoutubeCredential();
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Young']);

        for ($i = 1; $i <= 3; $i++) {
            $id = 'UCYNG00'.$i;
            $this->channels->activateFromDto($creator->id, $this->youtube, new PlatformChannelDto(
                platform: PlatformKey::Youtube,
                externalId: $id,
                handle: 'y'.$i,
                displayName: 'Y'.$i,
                description: null,
                thumbnailUrl: null,
                url: null,
            ));
            $this->pool->enqueue($this->youtube, $id, null, ChannelFetchPoolReason::Manual);
        }

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    ['id' => 'UCYNG001', 'snippet' => ['title' => 'F1', 'customUrl' => '@y1']],
                    ['id' => 'UCYNG002', 'snippet' => ['title' => 'F2', 'customUrl' => '@y2']],
                    ['id' => 'UCYNG003', 'snippet' => ['title' => 'F3', 'customUrl' => '@y3']],
                ],
            ], 200),
        ]);

        $skip = $this->pool->flush(PlatformKey::Youtube, force: false);
        $this->assertSame('skipped', $skip['platforms']['youtube']['status']);
        $this->assertSame(0, $skip['platforms']['youtube']['http']);
        $this->assertSame(3, ChannelFetchPoolItem::query()->where('status', ChannelFetchPoolStatus::Pending)->count());

        $forced = $this->pool->flush(PlatformKey::Youtube, force: true);
        $this->assertSame('flushed', $forced['platforms']['youtube']['status']);
        $this->assertSame(1, $forced['platforms']['youtube']['http']);
        $this->assertSame(3, ChannelFetchPoolItem::query()->where('status', ChannelFetchPoolStatus::Done)->count());
    }

    #[Test]
    public function coalesce_double_enqueue_keeps_single_pending_row(): void
    {
        $id = 'UCCOAL001';
        $this->pool->enqueue($this->youtube, $id, null, ChannelFetchPoolReason::Refresh);
        $this->pool->enqueue($this->youtube, $id, null, ChannelFetchPoolReason::Manual);

        $this->assertSame(1, ChannelFetchPoolItem::query()
            ->where('platform_id', $this->youtube->id)
            ->where('external_id', $id)
            ->whereIn('status', [ChannelFetchPoolStatus::Pending, ChannelFetchPoolStatus::Processing])
            ->count());
    }

    #[Test]
    public function second_flush_after_claim_does_not_http_again(): void
    {
        $this->activateYoutubeCredential();
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Once']);
        $id = 'UCONCE001';
        $this->channels->activateFromDto($creator->id, $this->youtube, new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: $id,
            handle: 'once',
            displayName: 'Once',
            description: null,
            thumbnailUrl: null,
            url: null,
        ));

        $item = $this->pool->enqueue($this->youtube, $id, null, ChannelFetchPoolReason::Manual);
        ChannelFetchPoolItem::query()->whereKey($item->id)->update([
            'requested_at' => now()->subMinutes(20),
        ]);

        $httpCalls = 0;
        Http::fake(function ($request) use (&$httpCalls, $id) {
            if (str_contains($request->url(), 'youtube/v3/channels')) {
                $httpCalls++;

                return Http::response([
                    'items' => [[
                        'id' => $id,
                        'snippet' => ['title' => 'Done', 'customUrl' => '@once'],
                    ]],
                ], 200);
            }

            return Http::response([], 404);
        });

        $this->pool->flush(PlatformKey::Youtube, force: false);
        $this->pool->flush(PlatformKey::Youtube, force: true);

        $this->assertSame(1, $httpCalls);
    }

    #[Test]
    public function skip_apply_on_archived_channel_still_marks_pool_done(): void
    {
        $this->activateYoutubeCredential();
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Arch']);
        $id = 'UCARCH001';
        $channel = $this->channels->activateFromDto($creator->id, $this->youtube, new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: $id,
            handle: 'arch',
            displayName: 'Before',
            description: null,
            thumbnailUrl: null,
            url: null,
        ));
        $this->channels->archive($user, $channel->id);

        $item = $this->pool->enqueue($this->youtube, $id, (string) $channel->id, ChannelFetchPoolReason::Manual);
        ChannelFetchPoolItem::query()->whereKey($item->id)->update([
            'requested_at' => now()->subMinutes(20),
        ]);

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [[
                    'id' => $id,
                    'snippet' => ['title' => 'ShouldNotApply', 'customUrl' => '@arch'],
                ]],
            ], 200),
        ]);

        $this->pool->flush(PlatformKey::Youtube, force: false);

        $channel->refresh();
        $this->assertSame(ChannelStatus::Archived, $channel->status);
        $this->assertSame('Before', $channel->display_name);
        $this->assertSame(ChannelFetchPoolStatus::Done, ChannelFetchPoolItem::query()->whereKey($item->id)->first()?->status);
    }

    private function activateYoutubeCredential(): void
    {
        $credential = $this->credentials->create($this->admin, $this->youtube, [
            'label' => 'yt-pool-test',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'yt-api-pool-key'],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' User',
            'email' => strtolower($role).'-pool-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
