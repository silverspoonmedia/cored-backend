<?php

namespace App\Domain\Service\Services;

use App\Domain\Currency\Services\ExchangeRateService;
use App\Domain\PaymentGateway\Services\Payment\InvoiceActiveCheckoutCancellationService;
use App\Domain\PaymentGateway\Services\Payment\InvoiceChannelFeeService;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Exceptions\ServiceException;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Support\Facades\DB;

class ServicePurchaseQuantityService
{
    public function __construct(
        private readonly ServiceCheckoutService $checkout,
        private readonly MainPlanChangeCalculator $planChange,
        private readonly ExchangeRateService $exchangeRates,
        private readonly InvoiceActiveCheckoutCancellationService $checkoutCancellation,
        private readonly InvoiceChannelFeeService $channelFees,
    ) {}

    public function updateQuantity(ServicePurchase $purchase, int $quantity): ServicePurchase
    {
        $purchase->loadMissing(['service.prices', 'invoice']);

        if ($purchase->status !== PurchaseStatus::Pending) {
            throw new ServiceException(__('api/services.quantity_not_editable'), 422);
        }

        $service = $purchase->service;

        if ($service === null || ! $service->isAddonPlan()) {
            throw new ServiceException(__('api/services.quantity_addon_only'), 422);
        }

        $invoice = $purchase->invoice;

        if ($invoice === null || ! in_array((string) $invoice->status, ['pending', 'challenge'], true)) {
            throw new ServiceException(__('api/services.quantity_not_editable'), 422);
        }

        $maxQuantity = max(1, (int) setting_config('service.purchase.max_quantity', 99));

        if ($quantity < 1 || $quantity > $maxQuantity) {
            throw new ServiceException(__('api/services.quantity_out_of_range', ['max' => $maxQuantity]), 422);
        }

        $this->checkout->assertAddonUnitPurchaseAllowed($purchase->user_id);

        $currency = strtoupper((string) $purchase->display_currency);
        $price = $service->priceFor($currency);

        if ($price === null) {
            throw new ServiceException(__('api/services.price_not_found', ['currency' => $currency]), 422);
        }

        $unitList = (float) $price->amount;
        $quote = $this->planChange->quote($service, (string) $purchase->user_id, $unitList);
        $unitCharge = $quote->chargeAmount;
        $listAmount = round($unitList * $quantity, 2);
        $chargeAmount = round($unitCharge * $quantity, 2);
        $settlement = (string) setting_config('service.settlement_currency', ExchangeRateService::SETTLEMENT_CURRENCY);

        if ($chargeAmount <= 0) {
            $fx = [
                'to' => $settlement,
                'amount' => 0.0,
                'rate' => 1.0,
                'provider' => 'zero_charge',
                'fetched_at' => now(),
                'snapshot_id' => null,
            ];
        } else {
            $fx = $this->exchangeRates->convert($chargeAmount, $currency, $settlement);
        }

        DB::transaction(function () use ($purchase, $invoice, $quantity, $quote, $listAmount, $chargeAmount, $fx): ServicePurchase {
            $purchase->update([
                'quantity' => $quantity,
                'list_amount' => $listAmount,
                'credit_amount' => 0,
                'charge_amount' => $chargeAmount,
                'display_amount' => $chargeAmount,
                'surplus_days' => 0,
                'previous_entitlement_id' => $quote->previousEntitlementId,
                'change_type' => $quote->changeType,
                'settlement_currency' => $fx['to'],
                'settlement_amount' => $fx['amount'],
                'fx_rate' => $fx['rate'],
                'fx_provider' => $fx['provider'],
                'fx_fetched_at' => $fx['fetched_at'],
                'fx_rate_snapshot_id' => $fx['snapshot_id'] ?? null,
            ]);

            $currency = strtoupper((string) $fx['to']);
            $amount = $currency === 'IDR'
                ? (float) round((float) $fx['amount'], 0)
                : round((float) $fx['amount'], 2);

            if ($this->checkoutCancellation->hasCheckoutSession($invoice)) {
                $this->checkoutCancellation->abandonActiveCheckout($invoice);
            }

            $invoice->refresh();
            $invoice->subtotal = $amount;
            $invoice->customer_gross_total = $amount;
            $this->channelFees->resetForFreshCheckout($invoice);
            $invoice->save();

            return $purchase->fresh(['service.prices', 'invoice.paymentGateway', 'invoice.paymentChannelRouteSnapshot', 'user']);
        });

        return $purchase;
    }
}
