<?php

namespace Tests\Domain\PaymentGateway\Unit;

use App\Domain\PaymentGateway\Enums\PaymentChannelKind;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransChargeParameterBuilder;
use App\Domain\PaymentGateway\Services\Payment\PaymentChannelResolver;
use App\Domain\PaymentGateway\Services\Payment\PaymentGatewayCredentialChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpPaymentChannels;
use Tests\TestCase;

class PaymentChannelResolverTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPaymentChannels;

    public function test_resolves_highest_priority_active_route(): void
    {
        $this->seedPaymentGatewaysAndChannels();

        $resolver = app(PaymentChannelResolver::class);
        $resolved = $resolver->resolve('qris');

        $this->assertNotNull($resolved);
        $this->assertSame('qris', $resolved['channel']->code);
        $this->assertSame('ipaymu', $resolved['gateway']->name);
        $this->assertSame(10, $resolved['route']->priority);
    }

    public function test_skips_inactive_channel(): void
    {
        $this->seedPaymentGatewaysAndChannels();

        PaymentChannel::query()->where('code', 'qris')->update(['is_active' => false]);

        $resolver = app(PaymentChannelResolver::class);

        $this->assertNull($resolver->resolve('qris'));
        $this->assertFalse(
            $resolver->listAvailable()->contains(fn ($channel) => $channel->code === 'qris'),
        );
    }

    public function test_falls_back_when_primary_gateway_not_configured(): void
    {
        $this->seedPaymentGatewaysAndChannels();

        config(['payment.gateways.ipaymu.api_key' => null]);

        $resolver = app(PaymentChannelResolver::class);
        $resolved = $resolver->resolve('qris');

        $this->assertNotNull($resolved);
        $this->assertSame('midtrans', $resolved['gateway']->name);
    }

    public function test_skips_inactive_route(): void
    {
        $this->seedPaymentGatewaysAndChannels();

        $channel = PaymentChannel::query()->where('code', 'qris')->firstOrFail();
        PaymentChannelRoute::query()
            ->where('payment_channel_id', $channel->id)
            ->whereHas('paymentGateway', fn ($q) => $q->where('name', 'ipaymu'))
            ->update(['is_active' => false]);

        $resolver = app(PaymentChannelResolver::class);
        $resolved = $resolver->resolve('qris');

        $this->assertNotNull($resolved);
        $this->assertSame('midtrans', $resolved['gateway']->name);
    }

    public function test_list_viable_routes_orders_by_priority(): void
    {
        $this->seedPaymentGatewaysAndChannels();

        $channel = PaymentChannel::query()->where('code', 'qris')->firstOrFail();
        $routes = app(PaymentChannelResolver::class)->listViableRoutes($channel);

        $this->assertCount(2, $routes);
        $this->assertSame(10, $routes->first()?->priority);
        $this->assertSame(20, $routes->last()?->priority);
    }
}
