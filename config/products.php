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
            'vtual.orphans.index',
            'vtual.platform-credentials.index',
            'vtual.profiles.index',
            'vtual.profiles.show',
        ],
    ],
    'Microstock' => [
        'activation_type' => 'implicit',
        // Standalone paid SKUs (free registration + compounding slot addon)
        // activate this product via Service fulfillment_target 'microstock'.
        // Storage still meters into the shared FH quota pool, but Microstock
        // workspace slots are accounted separately (MicrostockCatalog).
        'fulfillment_target' => 'microstock',
        // No dedicated sidebar menu (removed) — reached via /services and the
        // File Hosting workspace switcher. Empty menu_routes: nothing to hide.
        'menu_routes' => [],
    ],
];
