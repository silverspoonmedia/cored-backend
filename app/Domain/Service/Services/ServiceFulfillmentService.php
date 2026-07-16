<?php

namespace App\Domain\Service\Services;

use App\Domain\FileHosting\Models\UserQuota;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Support\Facades\Log;

class ServiceFulfillmentService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
    ) {}

    public function fulfill(ServicePurchase $purchase): void
    {
        $purchase->loadMissing('service');

        if ($purchase->status === PurchaseStatus::Paid) {
            return;
        }

        $result = $this->entitlements->activateFromPurchase($purchase);

        $purchase->status = PurchaseStatus::Paid;
        $purchase->period_start = $result['period_start'];
        $purchase->period_end = $result['period_end'];
        $purchase->save();

        $target = $purchase->service?->fulfillment_target;
        $entitlement = $purchase->service?->entitlement;

        if ($target === 'file_hosting' && $entitlement === 'unlimited_quota') {
            $this->grantUnlimitedFileHostingQuota($purchase);

            return;
        }

        if ($target !== null) {
            Log::info('service.fulfillment.unhandled_target', [
                'purchase_id' => $purchase->id,
                'fulfillment_target' => $target,
                'entitlement' => $entitlement,
            ]);
        }
    }

    public function markCancelled(ServicePurchase $purchase): void
    {
        if (in_array($purchase->status, [PurchaseStatus::Paid, PurchaseStatus::Cancelled], true)) {
            return;
        }

        $purchase->status = PurchaseStatus::Cancelled;
        $purchase->save();
    }

    public function markFailed(ServicePurchase $purchase): void
    {
        if ($purchase->status === PurchaseStatus::Paid) {
            return;
        }

        $purchase->status = PurchaseStatus::Failed;
        $purchase->save();
    }

    public function revokeFileHostingUnlimited(string $userId): void
    {
        UserQuota::query()
            ->where('user_id', $userId)
            ->where('source', 'service_purchase')
            ->delete();
    }

    private function grantUnlimitedFileHostingQuota(ServicePurchase $purchase): void
    {
        UserQuota::query()->updateOrCreate(
            ['user_id' => $purchase->user_id],
            [
                'quota_bytes' => 0,
                'source' => 'service_purchase',
                'source_id' => $purchase->id,
            ],
        );
    }
}
