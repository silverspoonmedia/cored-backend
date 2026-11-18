<?php

namespace App\Domain\Archivd\Support\Billing;

use App\Domain\Archivd\Support\ArchivdCatalog;
use App\Domain\Service\Database\Factories\ServiceFactory;
use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;
use App\Providers\Domain\ArchivdServiceProvider;

/**
 * Archivd's Service factory states, registered as macros on the package factory.
 *
 * The billing package owns a product-agnostic `ServiceFactory`; a SKU code and a
 * plan size are Archivd knowledge, so the states live here and the package stays
 * sellable without this product installed.
 *
 * Registered from {@see ArchivdServiceProvider::boot()}.
 */
final class ArchivdServiceStates
{
    public static function register(): void
    {
        ServiceFactory::macro('archivdMain1Tb', function (): ServiceFactory {
            return $this->plan([
                'code' => ArchivdCatalog::CODE_MAIN_1TB,
                'name' => 'Standard 1 TB',
                'description' => 'Main plan 1 TB monthly.',
                'billing_interval' => BillingInterval::Monthly,
                'fulfillment_target' => ArchivdCatalog::FULFILLMENT_TARGET,
                'entitlement' => 'storage_quota',
                'plan_kind' => PlanKind::Main,
                'storage_bytes' => ArchivdCatalog::main1TbBytes(),
                'status' => ServiceStatus::Active,
            ]);
        });

        ServiceFactory::macro('archivdMain2Tb', function (): ServiceFactory {
            return $this->plan([
                'code' => ArchivdCatalog::CODE_MAIN_2TB,
                'name' => 'Standard 2 TB',
                'description' => 'Main plan 2 TB monthly.',
                'billing_interval' => BillingInterval::Monthly,
                'fulfillment_target' => ArchivdCatalog::FULFILLMENT_TARGET,
                'entitlement' => 'storage_quota',
                'plan_kind' => PlanKind::Main,
                'storage_bytes' => ArchivdCatalog::main2TbBytes(),
                'status' => ServiceStatus::Active,
            ]);
        });

        ServiceFactory::macro('archivdAddon100Gb', function (): ServiceFactory {
            return $this->plan([
                'code' => ArchivdCatalog::CODE_ADDON_100GB,
                'name' => 'Standard +100 GB',
                'description' => 'Stackable +100 GB addon.',
                'billing_interval' => BillingInterval::Monthly,
                'fulfillment_target' => ArchivdCatalog::FULFILLMENT_TARGET,
                'entitlement' => 'storage_quota',
                'plan_kind' => PlanKind::Addon,
                'storage_bytes' => ArchivdCatalog::addon100GbBytes(),
                'status' => ServiceStatus::Active,
            ]);
        });

        ServiceFactory::macro('archivdAddon500Gb', function (): ServiceFactory {
            return $this->plan([
                'code' => ArchivdCatalog::CODE_ADDON_500GB,
                'name' => 'Standard +500 GB',
                'description' => 'Stackable +500 GB addon.',
                'billing_interval' => BillingInterval::Monthly,
                'fulfillment_target' => ArchivdCatalog::FULFILLMENT_TARGET,
                'entitlement' => 'storage_quota',
                'plan_kind' => PlanKind::Addon,
                'storage_bytes' => ArchivdCatalog::addon500GbBytes(),
                'status' => ServiceStatus::Active,
            ]);
        });

        ServiceFactory::macro('archivdAddonWorkspaceSlot', function (): ServiceFactory {
            return $this->plan([
                'code' => ArchivdCatalog::CODE_ADDON_WORKSPACE_SLOT,
                'name' => 'Team Workspace Slot',
                'description' => 'Stackable +1 owned team workspace slot.',
                'billing_interval' => BillingInterval::Monthly,
                'fulfillment_target' => ArchivdCatalog::FULFILLMENT_TARGET,
                'entitlement' => ArchivdCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
                'plan_kind' => PlanKind::Addon,
                'storage_bytes' => 0,
                'status' => ServiceStatus::Active,
            ]);
        });

        /** @deprecated Use archivdMain1Tb() — kept name for temporary test greps. */
        ServiceFactory::macro('archivdMonthly', function (): ServiceFactory {
            return $this->plan([
                'code' => ArchivdCatalog::CODE_MAIN_1TB,
                'name' => 'Standard 1 TB',
                'description' => 'Main plan 1 TB monthly.',
                'billing_interval' => BillingInterval::Monthly,
                'fulfillment_target' => ArchivdCatalog::FULFILLMENT_TARGET,
                'entitlement' => 'storage_quota',
                'plan_kind' => PlanKind::Main,
                'storage_bytes' => ArchivdCatalog::main1TbBytes(),
                'status' => ServiceStatus::Active,
            ]);
        });
    }
}
