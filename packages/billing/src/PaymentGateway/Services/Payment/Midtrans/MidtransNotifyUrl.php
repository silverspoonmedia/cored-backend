<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

use App\Domain\PaymentGateway\Services\Payment\PaymentProviderWebhookUrl;

final class MidtransNotifyUrl
{
    public static function webhookUrl(): string
    {
        return PaymentProviderWebhookUrl::resolve('midtrans');
    }
}
