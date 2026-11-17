<?php

namespace App\Domain\Microstock\Support\Billing;

use App\Domain\Microstock\Support\MicrostockCatalog;
use App\Domain\Service\Database\Factories\ServiceFactory;
use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;

/**
 * Microstock's Service factory states, registered as macros on the package factory.
 *
 * The billing package owns a product-agnostic `ServiceFactory`; a SKU code is
 * Microstock knowledge, so the states live here.
 *
 * Registered from {@see \App\Providers\Domain\MicrostockServiceProvider::boot()}.
 */
final class MicrostockServiceStates
{
    public static function register(): void
    {
        ServiceFactory::macro('microstockPlanFree', function (): ServiceFactory {
            return $this->plan([
                'code' => MicrostockCatalog::CODE_PLAN_FREE,
                'name' => 'Microstock Workspace (Free)',
                'description' => 'Free one-time Microstock registration.',
                'billing_interval' => BillingInterval::Once,
                'fulfillment_target' => MicrostockCatalog::FULFILLMENT_TARGET,
                'entitlement' => MicrostockCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
                'plan_kind' => PlanKind::Main,
                'storage_bytes' => 0,
                'status' => ServiceStatus::Active,
            ]);
        });

        ServiceFactory::macro('microstockAddonWorkspaceSlot', function (): ServiceFactory {
            return $this->plan([
                'code' => MicrostockCatalog::CODE_ADDON_WORKSPACE_SLOT,
                'name' => 'Microstock Workspace Slot',
                'description' => 'Stackable +1 Microstock workspace slot.',
                'billing_interval' => BillingInterval::Monthly,
                'fulfillment_target' => MicrostockCatalog::FULFILLMENT_TARGET,
                'entitlement' => MicrostockCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
                'plan_kind' => PlanKind::Addon,
                'storage_bytes' => 0,
                'status' => ServiceStatus::Active,
            ]);
        });

        ServiceFactory::macro('microstockAddonAiCredits5', function (): ServiceFactory {
            return $this->plan([
                'code' => MicrostockCatalog::CODE_ADDON_AI_CREDITS_5,
                'name' => 'Microstock AI Credits ($5)',
                'description' => 'One-time $5 prepaid AI analysis credits.',
                'billing_interval' => BillingInterval::Once,
                'fulfillment_target' => MicrostockCatalog::FULFILLMENT_TARGET,
                'entitlement' => MicrostockCatalog::ENTITLEMENT_AI_CREDITS,
                'plan_kind' => PlanKind::Addon,
                'storage_bytes' => 0,
                'status' => ServiceStatus::Active,
            ]);
        });
    }
}
