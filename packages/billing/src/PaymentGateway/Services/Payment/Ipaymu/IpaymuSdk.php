<?php

namespace App\Domain\PaymentGateway\Services\Payment\Ipaymu;

// Model
use App\Domain\PaymentGateway\Models\PaymentGateway;
// External
use iPaymu\iPaymu;

// Class
class IpaymuSdk
{
    public static function resolveGateway(): PaymentGateway
    {
        return PaymentGateway::query()->where('name', 'ipaymu')->firstOrFail();
    }

    public static function makeClient(PaymentGateway $gateway): iPaymu
    {
        self::assertConfigured($gateway);

        $cfg = $gateway->gatewayConfig();

        return new iPaymu(
            $cfg['api_key'],
            $cfg['va'],
            (bool) ($cfg['is_production'] ?? false)
        );
    }

    public static function assertConfigured(PaymentGateway $gateway): void
    {
        $cfg = $gateway->gatewayConfig();

        if (empty($cfg['api_key']) || empty($cfg['va'])) {
            throw new \RuntimeException('iPaymu API key or VA is not configured.');
        }
    }
}
