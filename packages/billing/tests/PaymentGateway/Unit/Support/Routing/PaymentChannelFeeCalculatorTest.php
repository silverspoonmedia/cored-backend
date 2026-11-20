<?php

namespace App\Domain\PaymentGateway\Tests\Unit\Support\Routing;

use App\Domain\PaymentGateway\Support\Routing\FeePolicyDefinition;
use App\Domain\PaymentGateway\Support\Routing\PaymentChannelFeeCalculator;
use App\Domain\PaymentGateway\Tests\TestCase;

class PaymentChannelFeeCalculatorTest extends TestCase
{
    public function test_flat_fee(): void
    {
        $calc = new PaymentChannelFeeCalculator;
        $fee = FeePolicyDefinition::fromMixed(3500);

        $this->assertSame(3500.0, $calc->estimateFromPolicy($fee, 250000));
    }

    public function test_percent_fee_rounds_idr(): void
    {
        $calc = new PaymentChannelFeeCalculator;
        $fee = FeePolicyDefinition::fromMixed('0.7%');

        $this->assertSame(1750.0, $calc->estimateFromPolicy($fee, 250000, 'IDR'));
    }

    public function test_percent_fee_two_percent(): void
    {
        $calc = new PaymentChannelFeeCalculator;
        $fee = FeePolicyDefinition::fromMixed('2%');

        $this->assertSame(5000.0, $calc->estimateFromPolicy($fee, 250000, 'IDR'));
    }
}
