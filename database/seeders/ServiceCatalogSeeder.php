<?php

namespace Database\Seeders;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Support\FileHostingCatalog;
use Illuminate\Database\Seeder;

/**
 * File Hosting catalog: paid mains (exclusive) + stackable addons.
 * Free tier is config behaviour only — not a catalog SKU.
 */
class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->retireLegacyUnlimited();
        $this->removeFreeCatalogSku();

        $this->seedPlan(
            code: FileHostingCatalog::CODE_MAIN_1TB,
            name: 'Standard 1 TB',
            description: 'Main File Hosting plan: 1 TB storage, billed monthly. Not stackable with other mains.',
            planKind: PlanKind::Main,
            storageBytes: FileHostingCatalog::main1TbBytes(),
            amountUsd: 24.95,
        );

        $this->seedPlan(
            code: FileHostingCatalog::CODE_MAIN_2TB,
            name: 'Standard 2 TB',
            description: 'Main File Hosting plan: 2 TB storage, billed monthly. Not stackable with other mains.',
            planKind: PlanKind::Main,
            storageBytes: FileHostingCatalog::main2TbBytes(),
            amountUsd: 54.95,
        );

        $this->seedPlan(
            code: FileHostingCatalog::CODE_ADDON_100GB,
            name: 'Standard +100 GB',
            description: 'Stackable storage addon: +100 GB per unit. Anniversary fixed from first buy; stack renews together.',
            planKind: PlanKind::Addon,
            storageBytes: FileHostingCatalog::addon100GbBytes(),
            amountUsd: 2.95,
        );

        $this->seedPlan(
            code: FileHostingCatalog::CODE_ADDON_500GB,
            name: 'Standard +500 GB',
            description: 'Stackable storage addon: +500 GB per unit. Anniversary fixed from first buy; stack renews together.',
            planKind: PlanKind::Addon,
            storageBytes: FileHostingCatalog::addon500GbBytes(),
            amountUsd: 14.50,
        );
    }

    private function removeFreeCatalogSku(): void
    {
        $free = Service::query()->where('code', FileHostingCatalog::CODE_FREE)->first();

        if ($free === null) {
            return;
        }

        $free->prices()->delete();
        $free->forceDelete();
    }

    private function retireLegacyUnlimited(): void
    {
        $legacy = Service::query()
            ->where('code', FileHostingCatalog::CODE_LEGACY_UNLIMITED)
            ->first();

        if ($legacy === null) {
            return;
        }

        $legacy->forceFill([
            'status' => ServiceStatus::Archived,
            'name' => 'File Hosting Subscription (retired)',
            'description' => 'Legacy unlimited POC — replaced by main/addon plans.',
        ])->save();
    }

    private function seedPlan(
        string $code,
        string $name,
        string $description,
        PlanKind $planKind,
        int $storageBytes,
        float $amountUsd,
    ): void {
        $service = Service::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => $description,
                'billing_interval' => BillingInterval::Monthly,
                'fulfillment_target' => FileHostingCatalog::FULFILLMENT_TARGET,
                'entitlement' => 'storage_quota',
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
    }
}
