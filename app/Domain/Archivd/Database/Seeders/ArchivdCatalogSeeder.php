<?php

namespace App\Domain\Archivd\Database\Seeders;

use App\Domain\Archivd\Support\ArchivdCatalog;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Support\ServiceCatalogWriter;
use Illuminate\Database\Seeder;

/**
 * Archivd slice of the service catalog: paid mains (exclusive) + stackable addons.
 *
 * Free tier is config behaviour only — not a catalog SKU.
 */
class ArchivdCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $writer = app(ServiceCatalogWriter::class);

        // A free catalog SKU is no longer sold; the free tier is a quota config.
        $writer->forget(ArchivdCatalog::CODE_FREE);

        $writer->retire(
            ArchivdCatalog::CODE_LEGACY_UNLIMITED,
            'Legacy unlimited POC — replaced by main/addon plans.',
        );

        $writer->seedPlan(
            code: ArchivdCatalog::CODE_MAIN_1TB,
            name: 'Standard 1 TB',
            description: 'Main Archivd plan: 1 TB storage, billed monthly. Not stackable with other mains.',
            planKind: PlanKind::Main,
            storageBytes: ArchivdCatalog::main1TbBytes(),
            amountUsd: 24.95,
            fulfillmentTarget: ArchivdCatalog::FULFILLMENT_TARGET,
        );

        $writer->seedPlan(
            code: ArchivdCatalog::CODE_MAIN_2TB,
            name: 'Standard 2 TB',
            description: 'Main Archivd plan: 2 TB storage, billed monthly. Not stackable with other mains.',
            planKind: PlanKind::Main,
            storageBytes: ArchivdCatalog::main2TbBytes(),
            amountUsd: 54.95,
            fulfillmentTarget: ArchivdCatalog::FULFILLMENT_TARGET,
        );

        $writer->seedPlan(
            code: ArchivdCatalog::CODE_ADDON_100GB,
            name: 'Standard +100 GB',
            description: 'Stackable storage addon: +100 GB per unit. Anniversary fixed from first buy; stack renews together.',
            planKind: PlanKind::Addon,
            storageBytes: ArchivdCatalog::addon100GbBytes(),
            amountUsd: 2.95,
            fulfillmentTarget: ArchivdCatalog::FULFILLMENT_TARGET,
        );

        $writer->seedPlan(
            code: ArchivdCatalog::CODE_ADDON_500GB,
            name: 'Standard +500 GB',
            description: 'Stackable storage addon: +500 GB per unit. Anniversary fixed from first buy; stack renews together.',
            planKind: PlanKind::Addon,
            storageBytes: ArchivdCatalog::addon500GbBytes(),
            amountUsd: 14.50,
            fulfillmentTarget: ArchivdCatalog::FULFILLMENT_TARGET,
        );

        $writer->seedPlan(
            code: ArchivdCatalog::CODE_ADDON_WORKSPACE_SLOT,
            name: 'Team Workspace Slot',
            description: 'Stackable +1 owned team workspace slot per unit. Does not add storage. Requires a paid Archivd plan for base slots.',
            planKind: PlanKind::Addon,
            storageBytes: 0,
            amountUsd: 1.00,
            entitlement: ArchivdCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
            fulfillmentTarget: ArchivdCatalog::FULFILLMENT_TARGET,
        );
    }
}
