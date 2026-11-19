<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Models\PaymentChannelRouteSnapshot;

class PaymentChannelRouteSnapshotService
{
    public function capture(Invoice $invoice, PaymentChannelRoute $route): PaymentChannelRouteSnapshot
    {
        if ($invoice->payment_channel_route_snapshot_id !== null) {
            $invoice->loadMissing('paymentChannelRouteSnapshot');
            /** @var PaymentChannelRouteSnapshot|null $existing */
            $existing = $invoice->paymentChannelRouteSnapshot;

            if ($existing !== null) {
                return $existing;
            }
        }

        $route->loadMissing('paymentChannel');
        /** @var PaymentChannel|null $channel */
        $channel = $route->paymentChannel;
        $channelCode = (string) ($channel->code ?? $invoice->payment_channel_code ?? '');

        $snapshot = PaymentChannelRouteSnapshot::fromRoute($route, $channelCode);
        $invoice->payment_channel_route_snapshot_id = $snapshot->id;

        return $snapshot;
    }
}
