<?php

namespace App\Domain\PaymentGateway\Support\Routing;

use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Models\PaymentChannelRouteSnapshot;

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

    public function estimateFromSnapshot(PaymentChannelRouteSnapshot $snapshot, float $subtotal, string $currency = 'IDR'): float
    {
        if ($snapshot->fee_flat !== null) {
            return $this->roundMoney((float) $snapshot->fee_flat, $currency);
        }

        if ($snapshot->fee_percent !== null) {
            $rate = (float) $snapshot->fee_percent / 100;

            return $this->roundMoney($subtotal * $rate, $currency);
        }

        return 0.0;
    }

    public function formatFeeLabel(PaymentChannelRoute $route): string
    {
        return $this->formatFeeLabelFromValues($route->fee_flat, $route->fee_percent);
    }

    public function formatFeeLabelFromSnapshot(PaymentChannelRouteSnapshot $snapshot): string
    {
        return $this->formatFeeLabelFromValues($snapshot->fee_flat, $snapshot->fee_percent);
    }

    private function formatFeeLabelFromValues(?int $feeFlat, mixed $feePercent): string
    {
        if ($feeFlat !== null) {
            return 'Rp '.number_format($feeFlat, 0, ',', '.');
        }

        if ($feePercent !== null) {
            $value = rtrim(rtrim(number_format((float) $feePercent, 3, '.', ''), '0'), '.');

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
