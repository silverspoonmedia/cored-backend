<?php

namespace App\Domain\Service\Services;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Contracts\InvoiceAttachableServiceInterface;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Exceptions\ServiceException;
use App\Domain\Service\Models\Currency;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Support\Facades\DB;

class ServiceCheckoutService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly InvoiceAttachableServiceInterface $invoices,
    ) {}

    /**
     * @return array{purchase: ServicePurchase, invoice: Invoice}
     */
    public function checkout(Service $service, User $user, string $currency): array
    {
        $currency = strtoupper(trim($currency));

        if (! $service->isActive()) {
            throw new ServiceException(__('api/services.not_available'), 422);
        }

        if (! Currency::query()->where('code', $currency)->where('is_active', true)->exists()) {
            throw new ServiceException(__('api/services.currency_not_supported', ['currency' => $currency]), 422);
        }

        $service->loadMissing('prices');
        $price = $service->priceFor($currency);

        if ($price === null) {
            throw new ServiceException(__('api/services.price_not_found', ['currency' => $currency]), 422);
        }

        $displayAmount = (float) $price->amount;
        $settlement = (string) config('service.settlement_currency', ExchangeRateService::SETTLEMENT_CURRENCY);
        $fx = $this->exchangeRates->convert($displayAmount, $currency, $settlement);

        return DB::transaction(function () use ($service, $user, $currency, $displayAmount, $fx): array {
            $purchase = ServicePurchase::query()->create([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'display_currency' => $currency,
                'display_amount' => $displayAmount,
                'settlement_currency' => $fx['to'],
                'settlement_amount' => $fx['amount'],
                'fx_rate' => $fx['rate'],
                'fx_provider' => $fx['provider'],
                'fx_fetched_at' => $fx['fetched_at'],
                'fx_rate_snapshot_id' => $fx['snapshot_id'] ?? null,
                'status' => PurchaseStatus::Pending,
            ]);

            $invoice = $this->invoices->createPendingInvoiceForServicePurchase($purchase, null);

            return [
                'purchase' => $purchase->load(['service.prices', 'invoice.paymentGateway', 'user']),
                'invoice' => $invoice,
            ];
        });
    }

    public function getPurchase(string $id): ServicePurchase
    {
        $purchase = ServicePurchase::query()
            ->with(['service.prices', 'invoice.paymentGateway', 'user'])
            ->findOrFail($id);

        $entitlement = ServiceEntitlement::query()
            ->where('user_id', $purchase->user_id)
            ->where('service_id', $purchase->service_id)
            ->first();

        $purchase->setRelation('entitlement', $entitlement);

        return $purchase;
    }
}
