<?php

/**
 * Product activation catalog.
 *
 * Runtime-access catalog for the Product Activation registry
 * (App\Support\Product). This is separate from config/domains.php,
 * which tracks module taxonomy / lifecycle / composition
 * (kind, locked, parent) only.
 *
 * Each entry declares:
 *
 *   activation_type    => 'implicit' | 'explicit'
 *     implicit  — auto-active on register; no provisioning step.
 *                 Backward-compatible with pre-registry users via
 *                 lazy-create on first middleware miss.
 *     explicit  — requires provisioning/approval before Active.
 *                 Starts Pending; admin approves/rejects.
 *
 *   fulfillment_target => string|null
 *     Maps this product to a Service catalog fulfillment_target
 *     (e.g. 'files' for a file-hosting product). Wired in
 *     ServiceFulfillmentService so paid entitlement activations can
 *     keep the activation row in sync. Null when the product has no
 *     paid SKU backing.
 *
 *   menu_routes        => list<string>
 *     Named menu routes that should be hidden from the dynamic
 *     navigation menu when the user's activation for this product
 *     is not Active (suspended/expired/pending). The visibility
 *     filter is enforced in MenuRepository::nodeVisibleLeafRules
 *     via ProductActivationService::isActive(). Empty/missing for
 *     products that have no menu surface.
 */

return [
];
