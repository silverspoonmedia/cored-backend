<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Models\PaymentGateway;

class PaymentGatewayCredentialChecker
{
    public function isConfigured(PaymentGateway $gateway): bool
    {
        $cfg = $gateway->gatewayConfig();

        return match ($gateway->name) {
            'midtrans' => ! empty($cfg['server_key']),
            'ipaymu' => ! empty($cfg['api_key']) && ! empty($cfg['va']),
            default => false,
        };
    }
}
