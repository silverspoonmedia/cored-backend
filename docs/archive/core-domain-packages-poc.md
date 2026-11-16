# Core domains as Composer path packages — POC decision

**Date:** 2026-11-16 · **Branch:** `refactor/core-domain-packages` · **Status:** accepted, POC complete

## Decision

`Navigation` and `Notification` — the two core domains with the fewest cross-domain imports — moved out of `app/Domain/*` into `packages/navigation` and `packages/notification`, required from the root manifest as `silverspoon/*` path packages. The `App\Domain\{Domain}\*` namespace, route names, config keys, migration timestamps and un-namespaced translation keys are unchanged. Product domains stay where they are.

The alternative — a second repository per domain, or a Composer registry — was rejected for this stage: a path package proves the consumption model (PSR-4 overlay, auto-discovered providers, own migrations/lang/tests) with no publishing pipeline, no versioning ceremony, and a one-line rollback.

## What the POC had to prove

1. The host boots with `app/Domain/Navigation` and `app/Domain/Notification` absent.
2. Both domains load from `packages/*` via a Composer path repository, still as `App\Domain\*`.
3. A class placed at `app/Domain/Navigation/...` beats the packaged copy.
4. Every gate stays green: `composer test`, `domain:list`, `route:list --path=api/v1` (204 routes before and after), locale guards, `docs-guard.php`, `migrate:fresh --seed`.

All four hold. `tests/Domain/Core/PackageOverrideTest.php` locks #2 and #3 so a later cleanup of the root autoload block cannot silently disable host overrides.

## Decisions worth remembering

- **Kernel contract over package import.** `DomainAccessService` used to call `MenuRepositoryInterface::bumpPresentedTreeEpoch()`. It now depends on `App\Support\Navigation\PresentedTreeEpoch`, declared by the kernel and bound by the package, behind `app()->bound()`. Importing a package interface from the kernel would have made the package mandatory and inverted the dependency.
- **`QueryRowActions` left the kernel.** `MenuQueryRowActions`, `MenuTrashedQueryRowActions`, `NotificationAudienceQueryRowActions` moved from `app/Support/ListActions/Builders/` into their domains. A kernel that renders domain rows is a domain dependency in disguise.
- **Explicit PSR-4 prefix is load-bearing.** Root `composer.json` declares `"App\\Domain\\Navigation\\": "app/Domain/Navigation/"`. Compose the map without it and the package entry is the only one; overrides become dead code with no error.
- **`version` in each package.** A path repository infers `dev-{branch}`, which fails `minimum-stability: stable` on any feature branch — exactly the branch this work happens on.
- **Lang stays un-namespaced.** `FileLoader::addPath()` merges the package tree with the host tree (`array_replace_recursive`, host wins), so no key changes and no vendor namespace appears in `__()`. All locale guards now scan `lang/` **plus** `packages/*/resources/lang`.
- **Seeders and factories take a domain namespace** (`App\Domain\{Domain}\Database\{Seeders,Factories}`), because `Database\*` is host-owned and would collide.
- **Product manifests point at package paths.** `Archivd`/`Microstock` `uninstall.json` entries now cite `packages/navigation/...`; removing a product never touches the package.

## Gotchas hit during the move

- `composer update` runs `@php artisan lang:update` (post-update hook), which rewrote every `lang/*` file. Those are not part of this change and were reverted; a future extraction on a branch that touches language files must re-check after any Composer run.
- Windows symlinks work (directory junction). If they ever fail, `"symlink": false` plus `composer update silverspoon/*` after each change under `packages/` is the fallback.
- `DomainUninstallManifestTest` currently fails on `docs:docs/archivd` because the sibling `docs` checkout is on `chore/drop-product-domains`, which already deleted that page. Pre-existing, unrelated, and reported rather than papered over.

## Not done (deliberately)

The remaining seven core domains (`Identity`, `ActivityLog`, `ErrorLog`, `PaymentGateway`, `Service`, `Inference`, `Ai`) stay in `app/Domain/*`. Identity is the RBAC hub every domain imports; PaymentGateway and Service carry product coupling. Extracting them is a separate decision, and this POC exists to make it an informed one rather than a batch edit.

Contract: [core-packages.md](../integration/core-packages.md). Registry detail: [architecture.md](../platform/architecture.md).
