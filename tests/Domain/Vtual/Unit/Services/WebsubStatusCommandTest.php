<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\WebsubSubscriptionStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\WebsubSubscription;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class WebsubStatusCommandTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
    }

    #[Test]
    public function reports_never_notified_active_subscriptions(): void
    {
        $channel = $this->seedChannel('UCSTATUS001');

        WebsubSubscription::query()->create([
            'channel_id' => $channel->id,
            'topic' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCSTATUS001',
            'callback_url' => 'https://example.test/cb',
            'secret' => 'x',
            'status' => WebsubSubscriptionStatus::Active,
            'lease_expires_at' => now()->addDays(5),
            'subscribed_at' => now()->subDay(),
            'last_notify_at' => null,
        ]);

        $this->artisan('vtual:websub-status')
            ->expectsOutputToContain('active_never_notified=1')
            ->assertSuccessful();
    }

    #[Test]
    public function notified_subscription_not_flagged(): void
    {
        $channel = $this->seedChannel('UCSTATUS002');

        WebsubSubscription::query()->create([
            'channel_id' => $channel->id,
            'topic' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCSTATUS002',
            'callback_url' => 'https://example.test/cb',
            'secret' => 'x',
            'status' => WebsubSubscriptionStatus::Active,
            'lease_expires_at' => now()->addDays(5),
            'subscribed_at' => now()->subDay(),
            'last_notify_at' => now(),
        ]);

        $this->artisan('vtual:websub-status')
            ->expectsOutputToContain('active_never_notified=0')
            ->assertSuccessful();
    }

    #[Test]
    public function empty_table_reports_no_subscriptions(): void
    {
        $this->artisan('vtual:websub-status')
            ->expectsOutputToContain('No WebSub subscriptions found')
            ->assertSuccessful();
    }

    private function seedChannel(string $externalId): Channel
    {
        $youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $user = User::query()->create([
            'name' => 'WS',
            'email' => 'ws-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('Admin');
        $creator = app(CreatorService::class)->createOrphan($user, ['display_name' => 'WS Fox']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $youtube->id,
            'external_id' => $externalId,
            'status' => ChannelStatus::Active,
        ]);
    }
}
