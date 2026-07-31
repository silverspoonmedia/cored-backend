<?php

namespace Tests\Domain\PaymentGateway\Unit\Support\Routing;

use App\Domain\PaymentGateway\Support\Routing\RoutingPolicyValidator;
use Tests\TestCase;

class RoutingPolicyValidatorTest extends TestCase
{
    public function test_rejects_invalid_gateway(): void
    {
        $validator = new RoutingPolicyValidator;

        $this->expectException(\InvalidArgumentException::class);

        $validator->validateRoot([
            'channels' => [
                'qris' => [
                    'type' => 'single',
                    'pg' => 'stripe',
                    'settlement' => 'H+2',
                    'fee' => '1%',
                ],
            ],
        ]);
    }
}
