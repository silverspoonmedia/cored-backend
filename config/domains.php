<?php

/**
 * Domain module registry (logical monorepo under app/Domain/*).
 *
 * Two axes (do not collapse them):
 *
 * kind  => 'core'    — foundation domain (Identity, Navigation, …). Never removable.
 *                      domain:remove uses hardcoded DomainManager::PLATFORM_CORE;
 *                      flipping kind in this file cannot unlock remove.
 * kind  => 'product' — SaaS / feature surface (FileHosting, …). Extends core.
 *
 * locked => true     — product only; domain:remove refuses until you manually set
 *                      locked => false in this file (no artisan unlock command).
 * locked => false    — product is removable (domain:make --removable).
 * (omit locked on core — not applicable)
 *
 * Removable is a derived state (product + locked false), not a config key.
 *
 * RBAC domain_kind (roles/permissions) is a separate concept — same words
 * (core|product) for catalog ownership, not this registry. See Identity docs.
 *
 * Provider classes still register in bootstrap/providers.php; Artisan domain:*
 * commands keep this file and providers/routes in sync.
 *
 * Convention: list core entries first, product entries last. Loaders
 * (routes/api/v1.php, routes/web.php web.auth, bootstrap/providers.php) follow
 * the same core → product order with comment sections — presentation only.
 */

return [
    // Core
    'Identity' => ['kind' => 'core'],
    'Navigation' => ['kind' => 'core'],
    'Notification' => ['kind' => 'core'],
    'ActivityLog' => ['kind' => 'core'],
    'ErrorLog' => ['kind' => 'core'],
    'PaymentGateway' => ['kind' => 'core'],
    'Service' => ['kind' => 'core'],

    // Product
    'FileHosting' => ['kind' => 'product', 'locked' => true],
];
