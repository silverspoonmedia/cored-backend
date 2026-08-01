<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Models\PaymentChannelRouteSnapshot;
use App\Domain\PaymentGateway\Support\Routing\PaymentChannelFeeCalculator;
use App\Support\Presentation\MoneyPresenter;

class InvoiceChannelFeeService
{
    public function __construct(
        private PaymentChannelFeeCalculator $calculator,
        private PaymentChannelResolver $channelResolver,
        private PaymentChannelRouteSnapshotService $routeSnapshots,
    ) {}

    public function estimate(PaymentChannelRoute $route, float $subtotal, string $currency = 'IDR'): float
    {
        return $this->calculator->estimateFromRoute($route, $subtotal, $currency);
    }

    /**
     * @return array{
     *     estimated_channel_fee: float,
     *     estimated_channel_fee_formatted: string|null,
     *     estimated_customer_total: float,
     *     estimated_customer_total_formatted: string|null,
     *     fee_label: string,
     *     gateway_name: string|null,
     *     route_role: string|null,
     * }|null
     */
    public function quoteForChannel(PaymentChannel $channel, float $subtotal, string $currency = 'IDR'): ?array
    {
        $route = $this->channelResolver->resolveRouteForChannel($channel);

        if ($route === null) {
            return null;
        }

        $route->loadMissing('paymentGateway');
        $fee = $this->estimate($route, $subtotal, $currency);
        $total = $subtotal + $fee;

        return array_merge([
            'estimated_channel_fee' => $fee,
            'estimated_channel_fee_formatted' => MoneyPresenter::formatted($fee, $currency),
            'estimated_customer_total' => $total,
            'estimated_customer_total_formatted' => MoneyPresenter::formatted($total, $currency),
            'fee_label' => $this->calculator->formatFeeLabel($route),
            'fee_flat' => $route->fee_flat !== null ? (float) $route->fee_flat : null,
            'fee_percent' => $route->fee_percent !== null ? (float) $route->fee_percent : null,
            'gateway_name' => $route->paymentGateway?->name,
            'route_role' => $route->route_role?->value ?? (is_string($route->route_role) ? $route->route_role : null),
        ], $this->feeBasisFromRoute($route));
    }

    public function applyBeforeCharge(Invoice $invoice, PaymentChannelRoute $route, string $gatewayName): void
    {
        if (! $this->buyerBearsFee()) {
            return;
        }

        if ($gatewayName !== 'midtrans') {
            return;
        }

        $snapshot = $this->routeSnapshots->capture($invoice, $route);
        $currency = (string) ($invoice->currency ?: 'IDR');
        $subtotal = (float) $invoice->subtotal;
        $fee = $this->calculator->estimateFromSnapshot($snapshot, $subtotal, $currency);

        $invoice->payment_channel_fee = $fee;
        $invoice->customer_gross_total = $subtotal + $fee;
        $invoice->save();
    }

    public function resetForFreshCheckout(Invoice $invoice): void
    {
        $subtotal = round((float) $invoice->subtotal, 2);

        $invoice->payment_channel_fee = 0;
        $invoice->customer_gross_total = $subtotal;
        $invoice->payment_channel_route_id = null;
        $invoice->payment_channel_route_snapshot_id = null;
    }

    public function buyerBearsFee(): bool
    {
        return config('payment.channel_fee_bearer', 'buyer') === 'buyer';
    }

    /**
     * @return array<string, mixed>
     */
    public function moneyBreakdown(Invoice $invoice): array
    {
        $currency = (string) ($invoice->currency ?: 'IDR');
        $invoice->loadMissing('paymentChannelRouteSnapshot');

        return array_merge([
            'subtotal' => (float) $invoice->subtotal,
            'subtotal_formatted' => MoneyPresenter::formatted($invoice->subtotal, $currency),
            'payment_channel_fee' => (float) $invoice->payment_channel_fee,
            'payment_channel_fee_formatted' => MoneyPresenter::formatted($invoice->payment_channel_fee, $currency),
            'customer_gross_total' => (float) $invoice->customer_gross_total,
            'customer_gross_total_formatted' => MoneyPresenter::formatted($invoice->customer_gross_total, $currency),
            'currency' => $currency,
        ], $this->feeBasisFields($invoice->paymentChannelRouteSnapshot));
    }

    /**
     * @return array{
     *     payment_channel_fee_type: string|null,
     *     payment_channel_fee_label: string|null,
     *     payment_channel_fee_basis: string|null,
     *     fee_type?: string|null,
     *     fee_percent?: float|null,
     * }
     */
    public function feeBasisFields(?PaymentChannelRouteSnapshot $snapshot): array
    {
        $basis = $this->feeBasisFromSnapshot($snapshot);

        return [
            'payment_channel_fee_type' => $basis['fee_type'],
            'payment_channel_fee_label' => $basis['fee_label'],
            'payment_channel_fee_basis' => $basis['fee_basis'],
        ];
    }

    /**
     * @return array{
     *     fee_type: string|null,
     *     fee_label: string|null,
     *     fee_percent: float|null,
     *     fee_basis: string|null,
     * }
     */
    private function feeBasisFromSnapshot(?PaymentChannelRouteSnapshot $snapshot): array
    {
        if ($snapshot === null) {
            return [
                'fee_type' => null,
                'fee_label' => null,
                'fee_percent' => null,
                'fee_basis' => null,
            ];
        }

        if ($snapshot->fee_flat !== null) {
            return [
                'fee_type' => 'flat',
                'fee_label' => $this->calculator->formatFeeLabelFromSnapshot($snapshot),
                'fee_percent' => null,
                'fee_basis' => null,
            ];
        }

        if ($snapshot->fee_percent !== null) {
            $label = $this->calculator->formatFeeLabelFromSnapshot($snapshot);

            return [
                'fee_type' => 'percent',
                'fee_label' => $label,
                'fee_percent' => (float) $snapshot->fee_percent,
                'fee_basis' => __('web/payments.checkout.fee_percent_basis', ['percent' => $label]),
            ];
        }

        return [
            'fee_type' => null,
            'fee_label' => null,
            'fee_percent' => null,
            'fee_basis' => null,
        ];
    }

    /**
     * @return array{
     *     fee_type: string|null,
     *     fee_label: string|null,
     *     fee_percent: float|null,
     *     fee_basis: string|null,
     * }
     */
    private function feeBasisFromRoute(?PaymentChannelRoute $route): array
    {
        if ($route === null) {
            return [
                'fee_type' => null,
                'fee_label' => null,
                'fee_percent' => null,
                'fee_basis' => null,
            ];
        }

        if ($route->fee_flat !== null) {
            return [
                'fee_type' => 'flat',
                'fee_label' => $this->calculator->formatFeeLabel($route),
                'fee_percent' => null,
                'fee_basis' => null,
            ];
        }

        if ($route->fee_percent !== null) {
            $label = $this->calculator->formatFeeLabel($route);

            return [
                'fee_type' => 'percent',
                'fee_label' => $label,
                'fee_percent' => (float) $route->fee_percent,
                'fee_basis' => __('web/payments.checkout.fee_percent_basis', ['percent' => $label]),
            ];
        }

        return [
            'fee_type' => null,
            'fee_label' => null,
            'fee_percent' => null,
            'fee_basis' => null,
        ];
    }
}
