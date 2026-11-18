<?php

namespace App\Domain\Service\Support;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;

/**
 * Upsert helper for the service catalog.
 *
 * The package owns the shape (plan + USD price); a product domain owns the rows
 * (its SKU codes, sizes and prices) and calls this from its own seeder.
 */
final class ServiceCatalogWriter
{
    /**
     * @param  string  $entitlement  entitlement key carried by this plan
     * @param  string  $fulfillmentTarget  product that fulfils the plan
     */
    public function seedPlan(
        string $code,
        string $name,
        string $description,
        PlanKind $planKind,
        int $storageBytes,
        float $amountUsd,
        string $entitlement = 'storage_quota',
        string $fulfillmentTarget = '',
        BillingInterval $interval = BillingInterval::Monthly,
    ): Service {
        $service = Service::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => $description,
                'billing_interval' => $interval,
                'fulfillment_target' => $fulfillmentTarget,
                'entitlement' => $entitlement,
                'plan_kind' => $planKind,
                'storage_bytes' => $storageBytes,
                'status' => ServiceStatus::Active,
            ],
        );

        ServicePrice::query()->updateOrCreate(
            [
                'service_id' => $service->id,
                'currency' => 'USD',
            ],
            [
                'amount' => $amountUsd,
            ],
        );

        return $service;
    }

    /**
     * Archive (not delete) a catalog row so an upgraded install stops selling it.
     */
    public function retire(string $code, string $description): void
    {
        $service = Service::query()->where('code', $code)->first();

        if ($service === null) {
            return;
        }

        $service->forceFill([
            'status' => ServiceStatus::Archived,
            'description' => $description,
        ])->save();
    }

    /**
     * Drop a catalog row and its prices — used for a SKU that must disappear.
     */
    public function forget(string $code): void
    {
        $service = Service::query()->where('code', $code)->first();

        if ($service === null) {
            return;
        }

        $service->prices()->delete();
        $service->forceDelete();
    }

    /**
     * Archive every code matching a LIKE pattern (wildcards allowed).
     */
    public function retirePattern(string $codeLike, string $description): void
    {
        $services = Service::query()->where('code', 'like', $codeLike)->get();

        foreach ($services as $service) {
            $service->forceFill([
                'status' => ServiceStatus::Archived,
                'description' => $description,
            ])->save();
        }
    }
}
