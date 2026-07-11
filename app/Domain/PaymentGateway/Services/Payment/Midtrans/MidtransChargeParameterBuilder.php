<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;

class MidtransChargeParameterBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function buildFromRoute(PaymentChannelRoute $route, Invoice $invoice): array
    {
        $paymentType = $route->provider_payment_type;
        $channel = $route->provider_payment_channel;

        $charge = ['payment_type' => $paymentType];

        if ($paymentType === 'bank_transfer' && is_string($channel) && $channel !== '') {
            $charge['bank_transfer'] = ['bank' => $channel];
        }

        if ($paymentType === 'qris') {
            $charge['qris'] = ['acquirer' => $channel ?: 'gopay'];
        }

        if ($paymentType === 'gopay') {
            $charge['gopay'] = ['enable_callback' => true];
        }

        if ($paymentType === 'shopeepay') {
            $charge['shopeepay'] = ['callback_url' => rtrim((string) config('app.url'), '/').'/payment/midtrans/shopeepay/callback'];
        }

        return $charge;
    }
}
