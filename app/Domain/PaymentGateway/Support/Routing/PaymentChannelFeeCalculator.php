<?php

namespace App\Domain\PaymentGateway\Support\Routing;

use App\Domain\PaymentGateway\Models\PaymentChannelRoute;

class PaymentChannelFeeCalculator
{
    public function estimateFromRoute(PaymentChannelRoute $route, float $subtotal, string $currency = 'IDR'): float
    {
        if ($route->fee_flat !== null) {
            return $this->roundMoney((float) $route->fee_flat, $currency);
        }

        if ($route->fee_percent !== null) {
            $rate = (float) $route->fee_percent / 100;

            return $this->roundMoney($subtotal * $rate, $currency);
        }

        return 0.0;
    }

    public function estimateFromPolicy(FeePolicyDefinition $fee, float $subtotal, string $currency = 'IDR'): float
    {
        if ($fee->isFlat()) {
            return $this->roundMoney((float) $fee->flatIdr, $currency);
        }

        if ($fee->isPercent()) {
            $rate = (float) $fee->percent / 100;

            return $this->roundMoney($subtotal * $rate, $currency);
        }

        return 0.0;
    }

    public function formatFeeLabel(PaymentChannelRoute $route): string
    {
        if ($route->fee_flat !== null) {
            return 'Rp '.number_format((int) $route->fee_flat, 0, ',', '.');
        }

        if ($route->fee_percent !== null) {
            $value = rtrim(rtrim(number_format((float) $route->fee_percent, 3, '.', ''), '0'), '.');

            return $value.'%';
        }

        return '—';
    }

    private function roundMoney(float $amount, string $currency): float
    {
        return strtoupper($currency) === 'IDR'
            ? (float) round($amount, 0)
            : round($amount, 2);
    }
}
