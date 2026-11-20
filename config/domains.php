<?php

/**
 * Domain module registry (logical monorepo under app/Domain/*).
 *
 * Two axes (do not collapse them):
 *
 * kind  => 'core'    — foundation domain (Identity, Navigation, …). Never removable.
 *                      domain:remove uses hardcoded DomainManager::PLATFORM_CORE;
 *                      flipping kind in this file cannot unlock remove.
 * kind  => 'product' — SaaS / feature surface (Archivd, …). Extends core.
 *
 * locked => true     — product only; domain:remove refuses until you manually set
 *                      locked => false in this file (no artisan unlock command).
 * locked => false    — product is removable (domain:make --removable).
 * (omit locked on core — not applicable)
 *
 * Removable is a derived state (product + locked false), not a config key.
 *
 * parent => 'Name'   — composition axis, product only. Declares this product a
 *                      SUBPRODUCT of another registered product domain (Microstock
 *                      extends Archivd). It is orthogonal to kind and locked: a
 *                      subproduct is still a full product (own key, activation,
 *                      RBAC, uninstall.json, namespace) — only its removal is
 *                      ordered. domain:remove refuses a parent while a registered
 *                      child points at it; domain:make --parent= sets it.
 *                      Never a third kind value — do not collapse the axes.
 * (omit parent on core, and on a product with no parent — not applicable)
 *
 * RBAC domain_kind (roles/permissions) is a separate concept — same words
 * (core|product) for catalog ownership, not this registry. See Identity docs.
 *
 * Provider classes still register in bootstrap/providers.php; Artisan domain:*
 * commands keep this file and providers/routes in sync.
 *
 * Convention: list core entries first, product entries last. Loaders
 * (routes/api/v1.php, routes/web.php server endpoints, bootstrap/providers.php)
 * follow the same core → product order with comment sections — presentation only.
 */

return [
    // Core — packaged domains declare where their source, routes, config and
    // translations live. The namespace stays App\Domain\{Name}\, so only the
    // location moved; a host may still override a class in app/Domain/{Name}/.
    'Identity' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/platform',
        'path' => 'packages/platform/src/Domain/Identity',
        'routes' => 'packages/platform/routes/identity.php',
        'web' => 'packages/platform/routes/web-identity-oauth-callback.php',
        'provider' => 'packages/platform/src/Providers/Domain/IdentityServiceProvider.php',
    ],
    'Navigation' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/navigation',
        'path' => 'packages/navigation/src',
        'routes' => 'packages/navigation/routes/api.php',
        'provider' => 'packages/navigation/src/NavigationServiceProvider.php',
    ],
    'Notification' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/notification',
        'path' => 'packages/notification/src',
        'routes' => 'packages/notification/routes/api.php',
        'provider' => 'packages/notification/src/NotificationServiceProvider.php',
    ],
    // One package, two core domains: ActivityLog + ErrorLog are one operational
    // surface (what happened vs what broke) and are consumed together, so they
    // share `silverspoonmedia/audit` while keeping separate namespaces and route files.
    'ActivityLog' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/audit',
        'path' => 'packages/audit/src',
        'routes' => 'packages/audit/routes/activity-log.php',
        'provider' => 'packages/audit/src/ActivityLog/AuditServiceProvider.php',
    ],
    'ErrorLog' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/audit',
        'path' => 'packages/audit/src',
        'routes' => 'packages/audit/routes/error-log.php',
        'provider' => 'packages/audit/src/ActivityLog/AuditServiceProvider.php',
    ],
    // One package, three core domains: Currency is the money vocabulary, Service
    // prices in it and settles through it, PaymentGateway collects the invoice —
    // one money surface, so they share `silverspoonmedia/billing` while keeping
    // separate namespaces and route files.
    'PaymentGateway' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/billing',
        'path' => 'packages/billing/src',
        'routes' => 'packages/billing/routes/payment-gateway.php',
        'web' => 'packages/billing/routes/web-ipaymu-return.php',
        'provider' => 'packages/billing/src/PaymentGateway/BillingServiceProvider.php',
    ],
    // Route file loads before `service.php` so the currency endpoints keep the
    // URI order they had when they shared that file.
    'Currency' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/billing',
        'path' => 'packages/billing/src',
        'routes' => 'packages/billing/routes/currency.php',
        'provider' => 'packages/billing/src/PaymentGateway/BillingServiceProvider.php',
    ],
    'Service' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/billing',
        'path' => 'packages/billing/src',
        'routes' => 'packages/billing/routes/service.php',
        'provider' => 'packages/billing/src/PaymentGateway/BillingServiceProvider.php',
    ],
    // One package, two core domains: Inference owns the catalog and the rates,
    // Ai owns the endpoints and keys that reach them; a rate is meaningless
    // without the connection that bills it, so they share `silverspoonmedia/ai`
    // while keeping separate namespaces and route files.
    'Inference' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/ai',
        'path' => 'packages/ai/src',
        'routes' => 'packages/ai/routes/inference.php',
        'provider' => 'packages/ai/src/Ai/AiServiceProvider.php',
    ],
    'Ai' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/ai',
        'path' => 'packages/ai/src',
        'routes' => 'packages/ai/routes/ai.php',
        'provider' => 'packages/ai/src/Ai/AiServiceProvider.php',
    ],

    // Product
    'Archivd' => ['kind' => 'product', 'locked' => true],

    // Subproduct (product with a parent)
    'Microstock' => ['kind' => 'product', 'locked' => true, 'parent' => 'Archivd'],
];
