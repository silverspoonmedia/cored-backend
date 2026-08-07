<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Jobs\RunChannelInitialContentBackfillJob;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\WebsubSubscription;
use App\Domain\Vtual\Services\ChannelActivationService;
use App\Domain\Vtual\Services\CreatorService;
use Carbon\Carbon;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ChannelActivationServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ChannelActivationService $activation;

    private CreatorService $creators;

    private Platform $youtube;

    private Platform $twitch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->activation = app(ChannelActivationService::class);
        $this->creators = app(CreatorService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
        Bus::fake();
        Http::fake([
            'pubsubhubbub.appspot.com/*' => Http::response('', 202),
        ]);
    }

    #[Test]
    public function activate_pending_youtube_dispatches_backfill_and_subscribes_websub(): void
    {
        $channel = $this->seedPendingChannel($this->youtube, 'UCACTIVATION001');
        $dto = $this->youtubeDto('UCACTIVATION001', 'activation');

        $result = $this->activation->activatePendingFromDto($channel, $this->youtube, $dto);

        $this->assertSame(ChannelStatus::Active, $result->status);
        $this->assertSame('UCACTIVATION001', $result->external_id);
        $this->assertNull($result->profile_input);

        Bus::assertDispatched(RunChannelInitialContentBackfillJob::class, function (RunChannelInitialContentBackfillJob $job) use ($channel): bool {
            return $job->channelId === (string) $channel->id;
        });

        $this->assertTrue(
            WebsubSubscription::query()->where('channel_id', $channel->id)->exists()
        );
    }

    #[Test]
    public function activate_pending_twitch_skips_youtube_hooks(): void
    {
        $channel = $this->seedPendingChannel($this->twitch, 'pending:'.Str::uuid7());
        $dto = new PlatformChannelDto(
            platform: PlatformKey::Twitch,
            externalId: '40934651',
            handle: 'ludwig',
            displayName: 'Ludwig',
            description: 'bio',
            thumbnailUrl: null,
            url: null,
        );

        $result = $this->activation->activatePendingFromDto($channel, $this->twitch, $dto);

        $this->assertSame(ChannelStatus::Active, $result->status);
        Bus::assertNotDispatched(RunChannelInitialContentBackfillJob::class);
        $this->assertSame(0, WebsubSubscription::query()->count());
    }

    private function seedPendingChannel(Platform $platform, string $externalId): Channel
    {
        $user = User::query()->create([
            'name' => 'Activation User',
            'email' => 'activation-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = $this->creators->createOrphan($user, ['display_name' => 'Activation Fox']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $platform->id,
            'external_id' => $externalId,
            'profile_input' => '@activation',
            'status' => ChannelStatus::Pending,
        ]);
    }

    private function youtubeDto(string $externalId, string $handle): PlatformChannelDto
    {
        return new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: $externalId,
            handle: $handle,
            displayName: 'Activation Channel',
            description: 'bio',
            thumbnailUrl: null,
            url: 'https://youtube.com/channel/'.$externalId,
            bannerUrl: null,
            platformCreatedAt: Carbon::parse('2017-04-19T14:57:55Z'),
        );
    }
}
