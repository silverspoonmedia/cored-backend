<?php

namespace App\Domain\Service\Services;

use App\Domain\PaymentGateway\Contracts\InvoiceAttachableServiceInterface;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServiceAddonRenewalLine;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Support\FileHostingCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-create pending full-stack addon renewal invoices at anniversary.
 */
class ServiceAddonRenewalService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly InvoiceAttachableServiceInterface $invoices,
        private readonly ServiceEntitlementService $entitlements,
    ) {}

    /**
     * @return array{created: int, skipped: int}
     */
    public function createDueRenewals(string $displayCurrency = 'USD'): array
    {
        $created = 0;
        $skipped = 0;

        $userIds = ServiceEntitlement::query()
            ->whereIn('status', [EntitlementStatus::Active, EntitlementStatus::Grace])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->whereHas('service', function ($q): void {
                // Storage + workspace-slot addons share stack renewal.
                $q->where('fulfillment_target', FileHostingCatalog::FULFILLMENT_TARGET)
                    ->where('plan_kind', PlanKind::Addon);
            })
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            try {
                $result = $this->createForUser((string) $userId, $displayCurrency);
                if ($result === null) {
                    $skipped++;
                } else {
                    $created++;
                }
            } catch (\Throwable $e) {
                Log::warning('service.addon_renewal.create_failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function createForUser(string $userId, string $displayCurrency = 'USD'): ?ServiceAddonRenewal
    {
        $addons = $this->entitlements->activeAddonEntitlements($userId);

        if ($addons->isEmpty()) {
            return null;
        }

        $due = $addons->first(fn (ServiceEntitlement $e): bool => $e->ends_at !== null && $e->ends_at->lessThanOrEqualTo(now()));

        if ($due === null) {
            return null;
        }

        $pendingExists = ServiceAddonRenewal::query()
            ->where('user_id', $userId)
            ->where('status', PurchaseStatus::Pending)
            ->exists();

        if ($pendingExists) {
            return null;
        }

        $displayCurrency = strtoupper($displayCurrency);
        $lines = [];
        $displayTotal = 0.0;
        $billingEndsBefore = null;

        foreach ($addons as $entitlement) {
            $service = $entitlement->service;
            if ($service === null) {
                continue;
            }

            $price = $service->priceFor($displayCurrency);
            if ($price === null) {
                Log::warning('service.addon_renewal.missing_price', [
                    'user_id' => $userId,
                    'service_id' => $service->id,
                    'currency' => $displayCurrency,
                ]);

                continue;
            }

            $qty = max(1, (int) $entitlement->quantity);
            $unit = (float) $price->amount;
            $line = round($unit * $qty, 2);
            $displayTotal += $line;

            $lines[] = [
                'service_id' => $service->id,
                'quantity' => $qty,
                'unit_amount' => $unit,
                'line_amount' => $line,
            ];

            if ($entitlement->ends_at !== null) {
                if ($billingEndsBefore === null || $entitlement->ends_at->lessThan($billingEndsBefore)) {
                    $billingEndsBefore = $entitlement->ends_at->copy();
                }
            }
        }

        if ($lines === [] || $displayTotal <= 0) {
            return null;
        }

        $settlement = (string) setting_config('service.settlement_currency', ExchangeRateService::SETTLEMENT_CURRENCY);
        $fx = $this->exchangeRates->convert($displayTotal, $displayCurrency, $settlement);

        return DB::transaction(function () use ($userId, $displayCurrency, $displayTotal, $fx, $lines, $billingEndsBefore): ServiceAddonRenewal {
            $renewal = ServiceAddonRenewal::query()->create([
                'user_id' => $userId,
                'status' => PurchaseStatus::Pending,
                'display_currency' => $displayCurrency,
                'display_amount' => $displayTotal,
                'settlement_currency' => $fx['to'],
                'settlement_amount' => $fx['amount'],
                'fx_rate' => $fx['rate'],
                'fx_provider' => $fx['provider'],
                'fx_fetched_at' => $fx['fetched_at'],
                'fx_rate_snapshot_id' => $fx['snapshot_id'] ?? null,
                'billing_ends_at_before' => $billingEndsBefore,
            ]);

            foreach ($lines as $line) {
                ServiceAddonRenewalLine::query()->create([
                    'service_addon_renewal_id' => $renewal->id,
                    'service_id' => $line['service_id'],
                    'quantity' => $line['quantity'],
                    'unit_amount' => $line['unit_amount'],
                    'line_amount' => $line['line_amount'],
                ]);
            }

            $this->invoices->createPendingInvoiceForServiceAddonRenewal($renewal, null);

            return $renewal->load(['lines.service', 'invoice', 'user']);
        });
    }
}
