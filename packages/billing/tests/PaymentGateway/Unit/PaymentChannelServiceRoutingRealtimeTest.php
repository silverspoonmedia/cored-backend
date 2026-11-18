<?php

namespace App\Domain\PaymentGateway\Tests\Unit;

use App\Domain\PaymentGateway\Events\PaymentRoutingCatalogChanged;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Services\PaymentChannelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsRolesAndPermissions;
use App\Domain\PaymentGateway\Tests\Concerns\SetsUpPaymentChannels;
use Tests\TestCase;

class PaymentChannelServiceRoutingRealtimeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;
    use SetsUpPaymentChannels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seedPaymentGatewaysAndChannels();
    }

    private function enableBroadcast(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);
    }

    public function test_update_channel_dispatches_routing_catalog_changed(): void
    {
        Event::fake([PaymentRoutingCatalogChanged::class]);
        $this->enableBroadcast();

        app(PaymentChannelService::class)->update('qris', ['is_active' => false]);

        Event::assertDispatched(PaymentRoutingCatalogChanged::class, function (PaymentRoutingCatalogChanged $e): bool {
            return $e->reason === 'channel_updated' && $e->channelCode === 'qris';
        });

        $this->assertFalse(PaymentChannel::query()->where('code', 'qris')->value('is_active'));
    }
}
