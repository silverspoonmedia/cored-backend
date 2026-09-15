<?php

namespace Database\Seeders;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Support\ArchivdCatalog;
use App\Domain\Service\Support\MicrostockCatalog;
use Illuminate\Database\Seeder;

/**
 * Archivd catalog: paid mains (exclusive) + stackable addons.
 * Free tier is config behaviour only — not a catalog SKU.
 */
class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->retireLegacyUnlimited();
        $this->removeFreeCatalogSku();

        $this->seedPlan(
            code: ArchivdCatalog::CODE_MAIN_1TB,
            name: 'Standard 1 TB',
            description: 'Main Archivd plan: 1 TB storage, billed monthly. Not stackable with other mains.',
            planKind: PlanKind::Main,
            storageBytes: ArchivdCatalog::main1TbBytes(),
            amountUsd: 24.95,
        );

        $this->seedPlan(
            code: ArchivdCatalog::CODE_MAIN_2TB,
            name: 'Standard 2 TB',
            description: 'Main Archivd plan: 2 TB storage, billed monthly. Not stackable with other mains.',
            planKind: PlanKind::Main,
            storageBytes: ArchivdCatalog::main2TbBytes(),
            amountUsd: 54.95,
        );

        $this->seedPlan(
            code: ArchivdCatalog::CODE_ADDON_100GB,
            name: 'Standard +100 GB',
            description: 'Stackable storage addon: +100 GB per unit. Anniversary fixed from first buy; stack renews together.',
            planKind: PlanKind::Addon,
            storageBytes: ArchivdCatalog::addon100GbBytes(),
            amountUsd: 2.95,
        );

        $this->seedPlan(
            code: ArchivdCatalog::CODE_ADDON_500GB,
            name: 'Standard +500 GB',
            description: 'Stackable storage addon: +500 GB per unit. Anniversary fixed from first buy; stack renews together.',
            planKind: PlanKind::Addon,
            storageBytes: ArchivdCatalog::addon500GbBytes(),
            amountUsd: 14.50,
        );

        $this->seedPlan(
            code: ArchivdCatalog::CODE_ADDON_WORKSPACE_SLOT,
            name: 'Team Workspace Slot',
            description: 'Stackable +1 owned team workspace slot per unit. Does not add storage. Requires a paid Archivd plan for base slots.',
            planKind: PlanKind::Addon,
            storageBytes: 0,
            amountUsd: 1.00,
            entitlement: ArchivdCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
        );

        $this->seedPlan(
            code: MicrostockCatalog::CODE_PLAN_FREE,
            name: 'Microstock Workspace (Free)',
            description: 'Free one-time Microstock registration: grants 1 built-in Microstock workspace slot. Auto-activates without payment.',
            planKind: PlanKind::Main,
            storageBytes: 0,
            amountUsd: 0.00,
            entitlement: MicrostockCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
            fulfillmentTarget: MicrostockCatalog::FULFILLMENT_TARGET,
            interval: BillingInterval::Once,
        );

        $this->seedPlan(
            code: MicrostockCatalog::CODE_ADDON_WORKSPACE_SLOT,
            name: 'Microstock Workspace Slot',
            description: 'Stackable +1 Microstock workspace slot per unit, billed monthly. Does not add storage. Slots are standalone from Archivd team workspace slots.',
            planKind: PlanKind::Addon,
            storageBytes: 0,
            amountUsd: 1.00,
            entitlement: MicrostockCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
            fulfillmentTarget: MicrostockCatalog::FULFILLMENT_TARGET,
        );

        $this->seedPlan(
            code: MicrostockCatalog::CODE_ADDON_AI_CREDITS_5,
            name: 'Microstock AI Credits ($5)',
            description: 'One-time prepaid AI analysis credits for Microstock metadata generation. $5 credit balance — debited only on successful analysis.',
            planKind: PlanKind::Addon,
            storageBytes: 0,
            amountUsd: 5.00,
            entitlement: MicrostockCatalog::ENTITLEMENT_AI_CREDITS,
            fulfillmentTarget: MicrostockCatalog::FULFILLMENT_TARGET,
            interval: BillingInterval::Once,
        );
    }

    private function removeFreeCatalogSku(): void
    {
        $free = Service::query()->where('code', ArchivdCatalog::CODE_FREE)->first();

        if ($free === null) {
            return;
        }

        $free->prices()->delete();
        $free->forceDelete();
    }

    private function retireLegacyUnlimited(): void
    {
        $legacy = Service::query()
            ->where('code', ArchivdCatalog::CODE_LEGACY_UNLIMITED)
            ->first();

        if ($legacy === null) {
            return;
        }

        $legacy->forceFill([
            'status' => ServiceStatus::Archived,
            'name' => 'Archivd Subscription (retired)',
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
        string $entitlement = 'storage_quota',
        string $fulfillmentTarget = ArchivdCatalog::FULFILLMENT_TARGET,
        BillingInterval $interval = BillingInterval::Monthly,
    ): void {
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
    }
}
