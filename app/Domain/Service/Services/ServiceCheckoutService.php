<?php

namespace App\Domain\Service\Services;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Contracts\InvoiceAttachableServiceInterface;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Exceptions\ServiceException;
use App\Domain\Service\Models\Currency;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Support\FileHostingCatalog;
use App\Domain\Service\Support\MicrostockCatalog;
use Illuminate\Support\Facades\DB;

class ServiceCheckoutService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly InvoiceAttachableServiceInterface $invoices,
        private readonly MainPlanChangeCalculator $planChange,
        private readonly ServiceFulfillmentService $fulfillment,
        private readonly ServiceEntitlementService $entitlements,
    ) {}

    /**
     * @return array{purchase: ServicePurchase, invoice: ?Invoice, quote: array<string, mixed>}
     */
    public function checkout(Service $service, User $user, string $currency, int $quantity = 1): array
    {
        $currency = strtoupper(trim($currency));
        $maxQuantity = max(1, (int) setting_config('service.purchase.max_quantity', 99));

        if ($quantity < 1 || $quantity > $maxQuantity) {
            throw new ServiceException(__('api/services.quantity_out_of_range', ['max' => $maxQuantity]), 422);
        }

        if (! $service->isAddonPlan() && $quantity !== 1) {
            throw new ServiceException(__('api/services.quantity_main_plan_fixed'), 422);
        }

        if (! $service->isActive()) {
            throw new ServiceException(__('api/services.not_available'), 422);
        }

        if (FileHostingCatalog::isFreeCode($service->code)) {
            throw new ServiceException(__('api/services.not_available'), 422);
        }

        // Microstock free registration is one-time: block rebuy once active.
        if (MicrostockCatalog::isFreeCode($service->code)
            && $this->entitlements->hasActiveMicrostockWorkspaceSlot($user->id)) {
            throw new ServiceException(__('api/services.already_activated'), 422);
        }

        if ($service->isAddonPlan()) {
            $this->assertCanBuyAddonUnit($user->id);
        }

        if (! Currency::query()->where('code', $currency)->where('is_active', true)->exists()) {
            throw new ServiceException(__('api/services.currency_not_supported', ['currency' => $currency]), 422);
        }

        $service->loadMissing('prices');
        $price = $service->priceFor($currency);

        if ($price === null) {
            throw new ServiceException(__('api/services.price_not_found', ['currency' => $currency]), 422);
        }

        $unitList = (float) $price->amount;
        $quote = $this->planChange->quote($service, $user->id, $unitList);
        $unitCharge = $quote->chargeAmount;
        $listAmount = $service->isAddonPlan()
            ? round($unitList * $quantity, 2)
            : $unitList;
        $chargeAmount = $service->isAddonPlan()
            ? round($unitCharge * $quantity, 2)
            : $unitCharge;
        $creditAmount = $service->isAddonPlan() && $quantity > 1 ? 0.0 : $quote->creditAmount;
        $surplusDays = $service->isAddonPlan() && $quantity > 1 ? 0 : $quote->surplusDays;
        $settlement = (string) setting_config('service.settlement_currency', ExchangeRateService::SETTLEMENT_CURRENCY);

        if ($quote->isZeroCharge()) {
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

        return DB::transaction(function () use ($service, $user, $currency, $quantity, $listAmount, $quote, $chargeAmount, $creditAmount, $surplusDays, $fx): array {
            $purchase = ServicePurchase::query()->create([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'quantity' => $quantity,
                'display_currency' => $currency,
                'display_amount' => $chargeAmount,
                'list_amount' => $listAmount,
                'credit_amount' => $creditAmount,
                'charge_amount' => $chargeAmount,
                'surplus_days' => $surplusDays,
                'previous_entitlement_id' => $quote->previousEntitlementId,
                'change_type' => $quote->changeType,
                'settlement_currency' => $fx['to'],
                'settlement_amount' => $fx['amount'],
                'fx_rate' => $fx['rate'],
                'fx_provider' => $fx['provider'],
                'fx_fetched_at' => $fx['fetched_at'],
                'fx_rate_snapshot_id' => $fx['snapshot_id'] ?? null,
                'status' => PurchaseStatus::Pending,
            ]);

            $invoice = null;

            if ($quote->isZeroCharge()) {
                // No Midtrans for $0 — fulfill immediately.
                $this->fulfillment->fulfill($purchase);
                $purchase->refresh();
            } else {
                $invoice = $this->invoices->createPendingInvoiceForServicePurchase($purchase, null);
            }

            return [
                'purchase' => $purchase->load(['service.prices', 'invoice.paymentGateway', 'invoice.paymentChannelRouteSnapshot', 'user']),
                'invoice' => $invoice ?? $purchase->invoice,
                'quote' => $quote->toArray(),
            ];
        });
    }

    public function getPurchase(string $id): ServicePurchase
    {
        $purchase = ServicePurchase::query()
            ->with(['service.prices', 'invoice.paymentGateway', 'invoice.paymentChannelRouteSnapshot', 'user'])
            ->findOrFail($id);

        $entitlement = ServiceEntitlement::query()
            ->where('user_id', $purchase->user_id)
            ->where('service_id', $purchase->service_id)
            ->first();

        $purchase->setRelation('entitlement', $entitlement);

        return $purchase;
    }

    /**
     * Addon unit buys blocked when stack anniversary is due or a renewal invoice is pending.
     */
    public function assertAddonUnitPurchaseAllowed(string $userId): void
    {
        $this->assertCanBuyAddonUnit($userId);
    }

    /**
     * Addon unit buys blocked when stack anniversary is due or a renewal invoice is pending.
     */
    private function assertCanBuyAddonUnit(string $userId): void
    {
        $pending = ServiceAddonRenewal::query()
            ->where('user_id', $userId)
            ->where('status', PurchaseStatus::Pending)
            ->exists();

        if ($pending) {
            throw new ServiceException(__('api/services.addon_renewal_pending'), 422);
        }

        if ($this->planChange->hasDueAddonAnniversary($userId)) {
            throw new ServiceException(__('api/services.addon_renewal_due'), 422);
        }
    }
}
