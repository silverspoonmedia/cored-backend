<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Jobs\RunChannelInitialContentBackfillJob;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\WebsubSubscription;
use App\Domain\Vtual\Services\ChannelRegistrationFlushService;
use App\Domain\Vtual\Services\ChannelService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformCredentialService;
use Carbon\Carbon;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ChannelRegistrationFlushServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ChannelRegistrationFlushService $registration;

    private ChannelService $channels;

    private CreatorService $creators;

    private Platform $youtube;

    private Platform $twitch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->registration = app(ChannelRegistrationFlushService::class);
        $this->channels = app(ChannelService::class);
        $this->creators = app(CreatorService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
        config([
            'vtual.channel_pool.threshold' => 50,
            'vtual.channel_pool.max_wait_minutes' => 15,
            'vtual.http.batch.channels' => 50,
        ]);
        Bus::fake();
    }

    #[Test]
    public function fifty_youtube_pending_triggers_flush_on_threshold(): void
    {
        $this->activateYoutubeCredential();
        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Batch Orphan']);

        $httpCalls = 0;
        Http::fake(function ($request) use (&$httpCalls) {
            if (str_contains($request->url(), 'pubsubhubbub.appspot.com')) {
                return Http::response('', 202);
            }

            if (str_contains($request->url(), 'youtube/v3/channels')) {
                $httpCalls++;
                $id = 'UCBATCH001';
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                if (! empty($query['id'])) {
                    $id = explode(',', (string) $query['id'])[0];
                }

                return Http::response([
                    'items' => [
                        [
                            'id' => $id,
                            'snippet' => [
                                'title' => 'Batch '.$id,
                                'description' => 'bio',
                                'customUrl' => '@batch',
                                'publishedAt' => '2017-04-19T14:57:55Z',
                                'thumbnails' => [],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        for ($i = 0; $i < 49; $i++) {
            $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
                'platform_key' => 'youtube',
                'profile' => $this->youtubeChannelId($i),
            ]);
        }

        $this->assertSame(0, $httpCalls);

        $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'youtube',
            'profile' => $this->youtubeChannelId(49),
        ]);

        $this->assertGreaterThanOrEqual(1, $httpCalls);
        $this->assertSame(
            50,
            Channel::query()
                ->where('platform_id', $this->youtube->id)
                ->where('status', ChannelStatus::Active)
                ->count()
        );
    }

    #[Test]
    public function flush_after_fifteen_minutes_without_fifty_pending(): void
    {
        $this->activateYoutubeCredential();
        $this->fakeYoutubeChannel($ageId = $this->youtubeChannelId(99), 'age', 'Age Channel');
        Http::fake([
            'pubsubhubbub.appspot.com/*' => Http::response('', 202),
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => $ageId,
                        'snippet' => [
                            'title' => 'Age Channel',
                            'description' => 'YouTube channel bio',
                            'customUrl' => '@age',
                            'publishedAt' => '2017-04-19T14:57:55Z',
                            'thumbnails' => [
                                'high' => ['url' => 'https://img.example/hi.jpg'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Age Orphan']);

        $channel = $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'youtube',
            'profile' => $ageId,
        ]);

        Channel::query()->whereKey($channel->id)->update([
            'created_at' => now()->subMinutes(16),
        ]);

        $summary = $this->registration->flush(force: false);
        $row = $summary['platforms']['youtube'];

        $this->assertSame('flushed', $row['status']);
        $this->assertSame(1, $row['applied']);
        $channel->refresh();
        $this->assertSame(ChannelStatus::Active, $channel->status);
        $this->assertSame($ageId, $channel->external_id);
        $this->assertNull($channel->profile_input);

        Bus::assertDispatched(RunChannelInitialContentBackfillJob::class, function (RunChannelInitialContentBackfillJob $job) use ($channel): bool {
            return $job->channelId === (string) $channel->id;
        });
        $this->assertTrue(
            WebsubSubscription::query()->where('channel_id', $channel->id)->exists()
        );
    }

    #[Test]
    public function forty_nine_youtube_and_one_twitch_do_not_cross_flush(): void
    {
        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => 'Cross Orphan']);

        Http::fake(fn () => Http::response([], 404));

        for ($i = 0; $i < 49; $i++) {
            $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
                'platform_key' => 'youtube',
                'profile' => $this->youtubeChannelId($i),
            ]);
        }

        $this->channels->registerPendingForStaff($this->admin, $orphan->id, [
            'platform_key' => 'twitch',
            'profile' => 'ludwig',
        ]);

        $summary = $this->registration->flush(force: false);

        $this->assertSame('skipped', $summary['platforms']['youtube']['status']);
        $this->assertSame('skipped', $summary['platforms']['twitch']['status']);
        $this->assertSame(50, Channel::query()->whereNotNull('profile_input')->count());
    }

    private function youtubeChannelId(int $index): string
    {
        return 'UC'.str_pad('BATCH'.$index, 22, '0', STR_PAD_LEFT);
    }

    private function fakeYoutubeChannel(string $id, string $handle, string $title): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => $id,
                        'snippet' => [
                            'title' => $title,
                            'description' => 'YouTube channel bio',
                            'customUrl' => '@'.$handle,
                            'publishedAt' => '2017-04-19T14:57:55Z',
                            'thumbnails' => [
                                'high' => ['url' => 'https://img.example/hi.jpg'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    private function activateYoutubeCredential(): void
    {
        $credential = app(PlatformCredentialService::class)->create($this->admin, $this->youtube, [
            'label' => 'yt-reg-flush',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'yt-reg-flush-key'],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'email' => 'u'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
