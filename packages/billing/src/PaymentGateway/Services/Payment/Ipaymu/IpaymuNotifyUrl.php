<?php

namespace App\Domain\PaymentGateway\Services\Payment\Ipaymu;

use App\Domain\PaymentGateway\Services\Payment\PaymentProviderWebhookUrl;

final class IpaymuNotifyUrl
{
    public static function webhookUrl(): string
    {
        return PaymentProviderWebhookUrl::resolve('ipaymu');
    }
}
