# Boilerplate forks (Cored)

Two private repos — `silverspoonmedia/cored-backend` and `silverspoonmedia/cored-frontend` — are the product-free pair a new project starts from. They are forked from this line **after** a release, with every product domain removed through `domain:remove` and its manifest, and they never carry Archivd or Microstock.

| | Backend | Frontend |
|---|---|---|
| Repo | `cored-backend` | `cored-frontend` |
| Forked from | `monolith` | `monolith-vue-workers` |
| Package source | Six private VCS mirrors, `^1.1` | — |
| Registry | Ten core domains, all `package` | `src/features/registry.ts`: `features: FeatureModule[] = []` |
| Brand | `APP_NAME=Cored` | `index.html` title, `APP_NAME`/`SITE_NAME`, sidebar mark |

`monolith` and `monolith-vue-workers` stay the **living line** — they keep the products and keep shipping. The forks are pulled forward by hand (`composer update silverspoonmedia/*`, `git pull` from upstream); nothing mirrors them.

## How a fork was made

Not a delete. Both copies ran the reviewed manifests:

1. Unlock the product entries in `config/domains.php` (`locked => true` → `false`), fork copy only.
2. Dry-run the manifests until `unresolved` and "references no manifest entry covers" are both empty.
3. `domain:remove Microstock --force`, then `domain:remove Archivd --force` — child first, the parent refuses while its subproduct is registered.
4. Sweep what the commands cannot see (locale packs, `.env` product keys, product-only dependencies), then swap the tree to the consumer shape: `client-scaffold/*` for the host directories, `cored/platform` for `packages/`, and the release tags for the path repositories.

The SPA half is the same manifest (`spa:` entries). The backend's scan reads the sibling directory name from `config/domains.php` `siblings.spa`, so a fork pair scans its own `cored-frontend` instead of reporting the SPA unverifiable forever.

## Extending it without editing a package

| Seam | Where a fork adds it |
|---|---|
| Settings group | `app/` `SettingContributor` + `SettingCatalog::register()` |
| Menu node | `app/` `MenuContributor` + `MenuContributorRegistry::register()` |
| AI consumer / ledger FK | `config(['ai.consumers' => …, 'ai.ledger_fk_targets' => …])` from the fork's provider |
| Upload proxy | `config(['storage.upload_proxy' => …])` |
| Kernel behavior | a class under the fork's `app/Support/...` — its PSR-4 prefix wins the race |
| SPA screen | `src/features/{name}/` implementing `FeatureModule`, one `registry.ts` line, one pack per live locale |

Two forks registering the same field keys do not collide: `SettingCatalog` merges definitions by registry key and the serialized path config carries the fork's own namespace. `scripts/run-vcs-consumer-check.php` proves all of it on a throwaway copy, including two colliding contributors, before each release.

| Code | Config | Tests | Pakem |
|---|---|---|---|
| `client-scaffold/` (backend host shape) · `src/features/` (SPA) | `config/domains.php` (`siblings`), `packages/platform/config/storage.php` | `ConsumerScaffoldTest`, `DomainUninstallManifestTest`, SPA `scripts/check-features.mjs` | [reference-fork-seams.md](reference-fork-seams.md) · [reference-package-seams.md](reference-package-seams.md) · [client-project.md](client-project.md) |
