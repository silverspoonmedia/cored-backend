<?php

namespace App\Domain\PaymentGateway\Tests\Unit\Support\Routing;

use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Support\Routing\PaymentRouteMaterializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\PaymentGateway\Tests\Concerns\SetsUpPaymentChannels;
use Tests\TestCase;

class PaymentRouteMaterializerTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPaymentChannels;

    public function test_materializes_policy_channels_and_routes(): void
    {
        $this->seedPaymentGatewayConfig();
        $this->seed(\App\Domain\PaymentGateway\Database\Seeders\PaymentGatewaySeeder::class);

        $stats = app(PaymentRouteMaterializer::class)->sync(dryRun: false);

        $this->assertGreaterThan(10, $stats['channels']);
        $this->assertGreaterThan(20, $stats['routes']);

        $qris = PaymentChannel::query()->where('code', 'qris')->with('routes.paymentGateway')->first();
        $this->assertNotNull($qris);

        $primary = $qris->routes->sortBy('priority')->first();
        $this->assertSame('midtrans', $primary?->paymentGateway?->name);

        $seabank = PaymentChannel::query()->where('code', 'va_seabank')->with('routes.paymentGateway')->first();
        $this->assertNotNull($seabank);
        $this->assertCount(1, $seabank->routes);
        $this->assertSame('midtrans', $seabank->routes->first()?->paymentGateway?->name);

        $gopay = PaymentChannel::query()->where('code', 'gopay')->with('routes.paymentGateway')->first();
        $this->assertNotNull($gopay);
        $this->assertSame('qris', $gopay->cheap_alt_code);
        $this->assertCount(1, $gopay->routes);
        $this->assertSame('midtrans', $gopay->routes->first()?->paymentGateway?->name);

        $this->assertNotNull(PaymentChannel::query()->where('code', 'dana')->first());
        $this->assertNotNull(PaymentChannel::query()->where('code', 'linkaja')->first());
    }
}
