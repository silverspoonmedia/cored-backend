<?php

namespace App\Domain\PaymentGateway\Tests\Unit\Support\Routing;

use App\Domain\PaymentGateway\Enums\ChannelRoutingType;
use App\Domain\PaymentGateway\Support\Routing\RoutingPolicyLoader;
use App\Domain\PaymentGateway\Support\Routing\RoutingPolicyValidator;
use App\Domain\PaymentGateway\Tests\TestCase;

class RoutingPolicyLoaderTest extends TestCase
{
    public function test_loads_production_yaml_policy(): void
    {
        $loader = new RoutingPolicyLoader(new RoutingPolicyValidator);
        $policy = $loader->load();

        $qris = $policy->qrisPolicy();
        $this->assertNotNull($qris);
        $this->assertSame(ChannelRoutingType::Dual, $qris->type);
        $this->assertSame('midtrans', $qris->routes[0]->gateway);
        $this->assertSame('ipaymu', $qris->routes[1]->gateway);
        $this->assertContains('ipaymu_h0', $qris->avoidTags);

        $va = $policy->vaPolicy();
        $this->assertNotNull($va);
        $this->assertSame('midtrans', $va->overrideGatewayForBank('seabank'));
        $this->assertSame('ipaymu', $va->overrideGatewayForBank('muamalat'));

        $gopay = $policy->channel('gopay');
        $this->assertNotNull($gopay);
        $this->assertSame('qris', $gopay->cheapAltCode);
        $this->assertSame(ChannelRoutingType::Single, $gopay->type);

        $linkaja = $policy->channel('linkaja');
        $this->assertNotNull($linkaja);
        $this->assertSame(ChannelRoutingType::QrisProxy, $linkaja->type);
    }
}
