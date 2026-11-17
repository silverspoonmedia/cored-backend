<?php

namespace App\Domain\Microstock\Database\Seeders;

use App\Domain\Microstock\Support\MicrostockCatalog;
use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Support\ServiceCatalogWriter;
use Illuminate\Database\Seeder;

/**
 * Microstock slice of the service catalog: free registration, workspace slots,
 * prepaid AI credit packs.
 */
class MicrostockCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $writer = app(ServiceCatalogWriter::class);

        $writer->seedPlan(
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

        $writer->seedPlan(
            code: MicrostockCatalog::CODE_ADDON_WORKSPACE_SLOT,
            name: 'Microstock Workspace Slot',
            description: 'Stackable +1 Microstock workspace slot per unit, billed monthly. Does not add storage. Slots are standalone from Archivd team workspace slots.',
            planKind: PlanKind::Addon,
            storageBytes: 0,
            amountUsd: 1.00,
            entitlement: MicrostockCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
            fulfillmentTarget: MicrostockCatalog::FULFILLMENT_TARGET,
        );

        $writer->seedPlan(
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
}
