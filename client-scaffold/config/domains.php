<?php

/**
 * Domain module registry — core only.
 *
 * A project built on `silverspoonmedia/*` installs the core domains as Composer
 * packages under `vendor/silverspoonmedia/{slug}/`. This file is what turns
 * those packages into routes, providers and a `domain:list`: `DomainManager`
 * resolves every path through it, so `routes/api/v1.php`, `routes/web.php` and
 * the console commands never hardcode a location.
 *
 * Product domains (Archivd, Microstock, …) are this repository's own sellable
 * surface and are not part of a consumer install. Adding one here would make
 * `domain:list` report a domain whose code is absent.
 *
 * Axes (see docs/platform/architecture.md in the monolith):
 *
 * kind  => 'core'   — foundation, never removable.
 * locked            — product only; omitted here.
 * parent            — product only; omitted here.
 *
 * Registry order is route load order. Keep the core order below: it matches
 * the monolith, so a path that worked there keeps its URI position here.
 * Never alphabetize.
 */

return [
    'Identity' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/platform',
        'path' => 'vendor/silverspoonmedia/platform/src/Domain/Identity',
        'routes' => 'vendor/silverspoonmedia/platform/routes/identity.php',
        'web' => 'vendor/silverspoonmedia/platform/routes/web-identity-oauth-callback.php',
        'provider' => 'vendor/silverspoonmedia/platform/src/Providers/Domain/IdentityServiceProvider.php',
    ],
    'Navigation' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/navigation',
        'path' => 'vendor/silverspoonmedia/navigation/src',
        'routes' => 'vendor/silverspoonmedia/navigation/routes/api.php',
        'provider' => 'vendor/silverspoonmedia/navigation/src/NavigationServiceProvider.php',
    ],
    'Notification' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/notification',
        'path' => 'vendor/silverspoonmedia/notification/src',
        'routes' => 'vendor/silverspoonmedia/notification/routes/api.php',
        'provider' => 'vendor/silverspoonmedia/notification/src/NotificationServiceProvider.php',
    ],
    // One package, two core domains: what happened vs what broke.
    'ActivityLog' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/audit',
        'path' => 'vendor/silverspoonmedia/audit/src',
        'routes' => 'vendor/silverspoonmedia/audit/routes/activity-log.php',
        'provider' => 'vendor/silverspoonmedia/audit/src/ActivityLog/AuditServiceProvider.php',
    ],
    'ErrorLog' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/audit',
        'path' => 'vendor/silverspoonmedia/audit/src',
        'routes' => 'vendor/silverspoonmedia/audit/routes/error-log.php',
        'provider' => 'vendor/silverspoonmedia/audit/src/ActivityLog/AuditServiceProvider.php',
    ],
    // One package, three core domains: the money vocabulary, the catalog priced
    // in it, the invoice that collects for it.
    'PaymentGateway' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/billing',
        'path' => 'vendor/silverspoonmedia/billing/src',
        'routes' => 'vendor/silverspoonmedia/billing/routes/payment-gateway.php',
        'web' => 'vendor/silverspoonmedia/billing/routes/web-ipaymu-return.php',
        'provider' => 'vendor/silverspoonmedia/billing/src/PaymentGateway/BillingServiceProvider.php',
    ],
    // Loads before `service.php` so the currency URIs keep their position.
    'Currency' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/billing',
        'path' => 'vendor/silverspoonmedia/billing/src',
        'routes' => 'vendor/silverspoonmedia/billing/routes/currency.php',
        'provider' => 'vendor/silverspoonmedia/billing/src/PaymentGateway/BillingServiceProvider.php',
    ],
    'Service' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/billing',
        'path' => 'vendor/silverspoonmedia/billing/src',
        'routes' => 'vendor/silverspoonmedia/billing/routes/service.php',
        'provider' => 'vendor/silverspoonmedia/billing/src/PaymentGateway/BillingServiceProvider.php',
    ],
    // One package, two core domains: the catalog with its rates, and the
    // connection that reaches them.
    'Inference' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/ai',
        'path' => 'vendor/silverspoonmedia/ai/src',
        'routes' => 'vendor/silverspoonmedia/ai/routes/inference.php',
        'provider' => 'vendor/silverspoonmedia/ai/src/Ai/AiServiceProvider.php',
    ],
    'Ai' => [
        'kind' => 'core',
        'package' => 'silverspoonmedia/ai',
        'path' => 'vendor/silverspoonmedia/ai/src',
        'routes' => 'vendor/silverspoonmedia/ai/routes/ai.php',
        'provider' => 'vendor/silverspoonmedia/ai/src/Ai/AiServiceProvider.php',
    ],
];
