<?php

namespace App\Domain\Service\Support;

/**
 * Microstock catalog codes and slot constants for Service × Microstock integration.
 *
 * Microstock workspace slots are standalone: they do NOT overlap with the
 * File Hosting team workspace slots (`FileHostingCatalog`). Storage still
 * meters into the shared FH quota pool, but slot accounting is separate.
 */
final class MicrostockCatalog
{
    public const FULFILLMENT_TARGET = 'microstock';

    /** Free one-time registration: grants the base Microstock workspace slot. */
    public const CODE_PLAN_FREE = 'microstock.plan.free';

    /** Stackable +1 Microstock workspace slot per unit ($1/month POC). */
    public const CODE_ADDON_WORKSPACE_SLOT = 'microstock.addon.workspace-slot.monthly';

    public const ENTITLEMENT_WORKSPACE_SLOTS = 'microstock_workspace_slots';

    /** Base Microstock workspaces included once the free registration is active. */
    public const BASE_WORKSPACE_SLOTS = 1;

    public static function isFreeCode(?string $code): bool
    {
        return $code === self::CODE_PLAN_FREE;
    }

    public static function isWorkspaceSlotCode(?string $code): bool
    {
        return $code === self::CODE_ADDON_WORKSPACE_SLOT;
    }
}
