# Billing is one package, not two — `silverspoon/billing`

**Date:** 2026-11-17 · **Branch:** `refactor/core-domain-packages` · **Status:** accepted, second bundle shipped

## Decision

> `PaymentGateway` + `Service` ship as **one** package, `silverspoon/billing`, with two namespaces, two API route files, one web route file, one provider.

The bundling rule from [core-packages-bundling-decision.md](core-packages-bundling-decision.md) already said a package is a distribution unit, not a taxonomy mirror. This is the second proof, and the first one that is not merely "two adjacent logging domains".

## Why not two packages

The pair is one **money surface**, in both directions:

| Edge | Direction | Example |
|------|-----------|---------|
| Entitlement is paid for by an invoice | `Service` → `PaymentGateway` | `ServicePurchase` / `ServiceAddonRenewal` are morph targets; `InvoiceAttachableService` writes both invoice shapes |
| Invoice fulfillment writes entitlements | `PaymentGateway` → `Service` | paid webhooks call `ServiceFulfillmentService`; the `service_purchase` morph lives on the invoice table |
| Shared tables | both | invoiceable columns, `currencies`, `service_prices` settlement in IDR |

Splitting them forces a cross-package runtime dependency with no versioning story, or a third contract package that exists only to break the cycle. Downstream consumers install the two halves together anyway — a tenant that sells subscriptions needs the catalog and the collector.

Two routes files stay because the **URL surfaces** are genuinely separate: `/api/v1/services*` and `/api/v1/payment-gateways|invoices|payments*` are disjoint, versioned independently in the SPA contract, and a host override of one must not move the other. One `routes` key per registry entry, same `package`/`path`/`provider`.

## The real blocker: coupling *into products*

`Service` did not merely couple to `PaymentGateway`. It imported product domains, which a core package may never do ([architecture.mdc](../../.cursor/rules/architecture.mdc)):

```
ServiceEntitlementService    → Archivd\QuotaService, ArchivdCatalog, ArchivdBillingPeriod
ServiceFulfillmentService    → ArchivdQuotaRecomputeService, Microstock\AiCreditService
ServiceEntitlementLifecycle  → EnforceFreeTierQuotaService, ArchivdQuotaRecomputeService, NotificationService
Service / MainPlanChange…    → ArchivdCatalog, MicrostockCatalog
```

The fix is the same shape as `silverspoon/audit`'s `SubjectAliasRegistrar`: **kernel declares, product implements, package consumes** — behind `app()->bound()`, and with an empty registry as a legal state.

| Kernel contract | Product implementation |
|---|---|
| `Support\Billing\FulfillmentRegistry` + `Contracts\FulfillmentHandler` + `FulfillmentContext` | `ArchivdFulfillmentHandler` (quota recompute, free-tier purge, fixed period, free SKU), `MicrostockFulfillmentHandler` (prepaid analysis credits) |
| `Contracts\TierObserver` | `ArchivdTierObserver` (tier analytics) |
| `Support\Notifications\Contracts\NotificationDispatcher` | adapter in `packages/notification` mapping a **string** type |
| `Support\Broadcasting\Contracts\ChannelRegistrar` | `PaymentGatewayChannelRegistrar`; host keeps `settings.currencies`, `logs.*`, RBAC |
| `Support\Encryption\Contracts\LegacyColumnMigrator` | `InvoicePayloadColumnMigrator`, so `EncryptionMigrateP0Command` stops importing `Invoice` |

The registry stores **class strings**, not instances: a long-lived Octane worker must not pin a product service, and an uninstalled product leaves an empty registry instead of a stale singleton.

## What else had to move

- **Catalog data left the package.** `ArchivdCatalog` / `MicrostockCatalog` are product constants; `ArchivdBillingPeriod` became the package-generic `App\Domain\Service\Support\BillingPeriod` (fixed 30 days), and a target that bills a different length declares it on its handler. The package keeps `ServiceCatalogWriter` so a product can still seed and retire its own SKUs.
- **Test fixtures stopped being product-aware.** `ServiceFactory` lost its `ArchivdMain1Tb()`-style states; products add them as `ServiceFactory::macro(...)` from their own provider (`ArchivdServiceStates`, `MicrostockServiceStates`). Package tests use neutral fixtures (`TestPlans`, `FixedPeriodTestHandler`).
- **Product-scenario tests moved to the product.** `ArchivdBilling*`, `ArchivdAdminJourneyScenarioTest`, `ArchivdPlanQuotaMatrixTest`, `MainPlanFairnessFulfillmentTest`, `ServiceEntitlementLifecycleTest`, `AddonAnniversaryTest` now live in `tests/Domain/Archivd/Unit/Billing/` — they exercise the handler, not the package. A moved file that still passes with the handler deleted was moved for the wrong reason.
- **The payment-return web route is registry-driven.** `DomainManager::packageWebRoutePaths()` reads a `web` key, so `routes/web.php` no longer hardcodes `routes/web/payment-ipaymu-return.php` — the `web` middleware group is unchanged.
- **Routing YAML is package data.** `resources/routing/price.yaml`, still addressed by the host-owned `config/payment-routing.php`, so a host can point at its own file without forking the package.

## Invariants this locks

1. Two PSR-4 prefixes (`App\Domain\PaymentGateway\`, `App\Domain\Service\`) and two test prefixes; bundling never merges namespaces.
2. `PackageOverrideTest` proves both resolve from `packages/billing`, both name `silverspoon/billing`, both API route files load, the API-route file for `Service` does not carry the gateway's, the `web` file loads through the registry, both host prefixes win the PSR-4 race, and `FulfillmentRegistry` resolves a handler after product providers boot **without the test naming any product**.
3. Locale keys did not change: `api/payments.*`, `api/services.*`, `api/currencies.*` now resolve from `packages/billing/resources/lang/{locale}/api/`. The seven-locale parity, prose, raw-key, glossary, and curation-flag guards stay green — moving a file is not a translation change.
4. `DomainUninstallManifestTest` stays green: both product manifests cite `packages/billing/**` for files that used to sit under `app/Domain/{PaymentGateway,Service}/`, and package tests a product scenario used to own are recorded at their new path.
5. Route set and names are identical before and after — the gate is the `route:list` diff, not intuition (the registry loads billing before `inference`/`ai`, but the URI prefixes are disjoint).

Contract: [core-packages.md](../integration/core-packages.md). Registry: [architecture.md](../platform/architecture.md). Domain briefs: [payment-gateway](../domains/payment-gateway/README.md) · [service](../domains/service/README.md).
