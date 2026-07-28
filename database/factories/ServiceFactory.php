<?php

namespace Database\Factories;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Support\FileHostingCatalog;
use App\Domain\Service\Support\MicrostockCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'svc.'.Str::lower(Str::random(8)),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => null,
            'entitlement' => null,
            'plan_kind' => null,
            'storage_bytes' => 0,
            'status' => ServiceStatus::Active,
        ];
    }

    public function fileHostingMain1Tb(): static
    {
        return $this->state(fn (): array => [
            'code' => FileHostingCatalog::CODE_MAIN_1TB,
            'name' => 'Standard 1 TB',
            'description' => 'Main plan 1 TB monthly.',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => FileHostingCatalog::FULFILLMENT_TARGET,
            'entitlement' => 'storage_quota',
            'plan_kind' => PlanKind::Main,
            'storage_bytes' => FileHostingCatalog::main1TbBytes(),
            'status' => ServiceStatus::Active,
        ]);
    }

    public function fileHostingMain2Tb(): static
    {
        return $this->state(fn (): array => [
            'code' => FileHostingCatalog::CODE_MAIN_2TB,
            'name' => 'Standard 2 TB',
            'description' => 'Main plan 2 TB monthly.',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => FileHostingCatalog::FULFILLMENT_TARGET,
            'entitlement' => 'storage_quota',
            'plan_kind' => PlanKind::Main,
            'storage_bytes' => FileHostingCatalog::main2TbBytes(),
            'status' => ServiceStatus::Active,
        ]);
    }

    public function fileHostingAddon100Gb(): static
    {
        return $this->state(fn (): array => [
            'code' => FileHostingCatalog::CODE_ADDON_100GB,
            'name' => 'Standard +100 GB',
            'description' => 'Stackable +100 GB addon.',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => FileHostingCatalog::FULFILLMENT_TARGET,
            'entitlement' => 'storage_quota',
            'plan_kind' => PlanKind::Addon,
            'storage_bytes' => FileHostingCatalog::addon100GbBytes(),
            'status' => ServiceStatus::Active,
        ]);
    }

    public function fileHostingAddon500Gb(): static
    {
        return $this->state(fn (): array => [
            'code' => FileHostingCatalog::CODE_ADDON_500GB,
            'name' => 'Standard +500 GB',
            'description' => 'Stackable +500 GB addon.',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => FileHostingCatalog::FULFILLMENT_TARGET,
            'entitlement' => 'storage_quota',
            'plan_kind' => PlanKind::Addon,
            'storage_bytes' => FileHostingCatalog::addon500GbBytes(),
            'status' => ServiceStatus::Active,
        ]);
    }

    public function fileHostingAddonWorkspaceSlot(): static
    {
        return $this->state(fn (): array => [
            'code' => FileHostingCatalog::CODE_ADDON_WORKSPACE_SLOT,
            'name' => 'Team Workspace Slot',
            'description' => 'Stackable +1 owned team workspace slot.',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => FileHostingCatalog::FULFILLMENT_TARGET,
            'entitlement' => FileHostingCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
            'plan_kind' => PlanKind::Addon,
            'storage_bytes' => 0,
            'status' => ServiceStatus::Active,
        ]);
    }

    public function microstockPlanFree(): static
    {
        return $this->state(fn (): array => [
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
    }

    public function microstockAddonWorkspaceSlot(): static
    {
        return $this->state(fn (): array => [
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
    }

    public function microstockAddonAiCredits5(): static
    {
        return $this->state(fn (): array => [
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
    }

    /** @deprecated Use fileHostingMain1Tb() — kept name for temporary test greps. */
    public function fileHostingMonthly(): static
    {
        return $this->fileHostingMain1Tb();
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => ServiceStatus::Draft]);
    }
}
