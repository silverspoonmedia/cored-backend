<?php

/**
 * Product activation catalog.
 *
 * Runtime-access catalog for the Product Activation registry
 * (App\Support\Product). This is separate from config/domains.php,
 * which tracks module taxonomy / lifecycle (kind, locked) only.
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
 *     (e.g. 'file_hosting'). Wired in ServiceFulfillmentService so
 *     paid entitlement activations can keep the activation row in
 *     sync. Null when the product has no paid SKU backing.
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
    'FileHosting' => [
        'activation_type' => 'implicit',
        'fulfillment_target' => 'file_hosting',
        'menu_routes' => ['file-hosting.index'],
    ],
    'Vtual' => [
        'activation_type' => 'implicit',
        'fulfillment_target' => null,
        'menu_routes' => [
            'vtual.index',
            'vtual.profiles.index',
            'vtual.claims.index',
            'vtual.claims.staff',
            'vtual.admin.credentials.index',
        ],
    ],
];
