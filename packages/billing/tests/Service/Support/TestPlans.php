<?php

namespace App\Domain\Service\Tests\Support;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;

/**
 * Neutral catalog rows for package tests.
 *
 * The package must not import a product catalog, so tests describe plans with
 * plain attributes instead of a product SKU constant. Product-specific
 * scenarios live in the owning product's test tree.
 */
final class TestPlans
{
    public const GIB = 1024 * 1024 * 1024;

    public const TIB = 1024 * 1024 * 1024 * 1024;

    /**
     * @return array<string, mixed>
     */
    public static function main1Tb(): array
    {
        return [
            'code' => 'test.plan.1tb.monthly',
            'name' => 'Standard 1 TB',
            'description' => 'Main plan 1 TB monthly.',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => 'test_target',
            'entitlement' => 'storage_quota',
            'plan_kind' => PlanKind::Main,
            'storage_bytes' => self::TIB,
            'status' => ServiceStatus::Active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function main2Tb(): array
    {
        return [
            'code' => 'test.plan.2tb.monthly',
            'name' => 'Standard 2 TB',
            'description' => 'Main plan 2 TB monthly.',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => 'test_target',
            'entitlement' => 'storage_quota',
            'plan_kind' => PlanKind::Main,
            'storage_bytes' => 2 * self::TIB,
            'status' => ServiceStatus::Active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function addon100Gb(): array
    {
        return [
            'code' => 'test.addon.100gb.monthly',
            'name' => 'Standard +100 GB',
            'description' => 'Stackable +100 GB addon.',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => 'test_target',
            'entitlement' => 'storage_quota',
            'plan_kind' => PlanKind::Addon,
            'storage_bytes' => 100 * self::GIB,
            'status' => ServiceStatus::Active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function addon500Gb(): array
    {
        return [
            'code' => 'test.addon.500gb.monthly',
            'name' => 'Standard +500 GB',
            'description' => 'Stackable +500 GB addon.',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => 'test_target',
            'entitlement' => 'storage_quota',
            'plan_kind' => PlanKind::Addon,
            'storage_bytes' => 500 * self::GIB,
            'status' => ServiceStatus::Active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function workspaceSlotAddon(): array
    {
        return [
            'code' => 'test.addon.workspace-slot.monthly',
            'name' => 'Team Workspace Slot',
            'description' => 'Stackable +1 workspace slot.',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => 'test_target',
            'entitlement' => 'workspace_slots',
            'plan_kind' => PlanKind::Addon,
            'storage_bytes' => 0,
            'status' => ServiceStatus::Active,
        ];
    }
}
