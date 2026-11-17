<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

// Model
use App\Domain\PaymentGateway\Models\PaymentGateway;

// External
use Midtrans\Config;

// Class
class MidtransSdk
{
    public static function resolveGateway(): PaymentGateway
    {
        return PaymentGateway::query()->where('name', 'midtrans')->firstOrFail();
    }

    public static function configure(PaymentGateway $gateway): void
    {
        $cfg = $gateway->gatewayConfig();

        Config::$serverKey = $cfg['server_key'] ?? '';
        Config::$clientKey = $cfg['client_key'] ?? '';
        Config::$isProduction = (bool) ($cfg['is_production'] ?? false);
    }

    public static function assertConfigured(PaymentGateway $gateway): void
    {
        $cfg = $gateway->gatewayConfig();

        if (empty($cfg['server_key'])) {
            throw new \RuntimeException('Midtrans server key is not configured.');
        }
    }
}
