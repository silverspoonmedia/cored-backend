# Reference — a fork's product seams and removal recipe

Deep dive for [client-project.md](client-project.md). A fork runs with **no product domain**: every seam below is a kernel registry or published config key the product fills from its own provider, so deleting the domain deletes the registration and the kernel falls back to its defaults. `KernelProductNeutralityTest` fails on a product name under `packages/*/{src,config,routes,database}`.

| Seam | Contract / key | Owner | Product registers from | Empty state |
|---|---|---|---|---|
| Settings | `Support\Settings\Contracts\SettingContributor` → `definitions()` | `Support\Settings\SettingCatalog` (singleton; `SettingRegistry` is a static facade over it) | `Archivd/…/ArchivdSettingContributor`, `Microstock/…/MicrostockSettingContributor` in `register()` behind `app()->bound()` | 37 `platform.*` definitions only |
| Menu | `Support\Navigation\Contracts\MenuContributor` → `contributions()` keyed by group (`products`, `settings`) | `Support\Navigation\MenuContributorRegistry`; `packages/navigation` `MenuSeeder` appends after the core nodes of that group | `Archivd/…/ArchivdMenuContributor`, `Microstock/…/MicrostockMenuContributor` | core tree (`HostMenuSeederTreeTest` pins merged order) |
| AI consumers + FKs | `config('ai.consumers')`, `config('ai.ledger_fk_targets')` (`[{table, columns[]}]`) | `packages/ai` — seeder, `ImportLegacyEnvCommand`, inference migration (`Schema::hasTable()`) | `Microstock\Support\Ai\MicrostockAiConfig` merged in `boot()` | no consumer rows, no extra FKs |
| Storage | `packages/platform/config/storage.php` — `upload_proxy.*`, `debug_log_requests` | `Support\Storage\S3DirectUploadEngine` reads `storage.*` | `ArchivdServiceProvider::boot()` republishes `config('archivd.*')` | engine inert, no proxy URL |
| Share URL | kernel keeps `to()`/`passwordReset()`/`emailVerification()`/`paymentReturn()`/`paymentCancel()` | `Support\SpaPublicUrl` | `Support\PublicShareUrl` owns `/s/f/{id}`, `/s/d/{id}` | no share link, `/s/*` 404 |
| SPA feature | `src/features/types.ts` `FeatureModule` | `src/features/registry.ts` + `src/features/messages.ts` | `src/features/{name}/index.ts` (routes, `boot()`, `messages` loader) | core screens only, core locale pack |

Product copy travels with the product on both sides: menu labels read `trans('web/{domain}.menu.*')` from the domain's own lang files, a setting's validation rules ride on its definition (`rules`), so `UpdateSettingsGroupRequest` keeps no `match ($key)` list, and a feature's locale keys live in `src/i18n/features/{name}/`, never the core pack.

## Fork recipe: dropping a product

Nothing under `packages/` changes.

1. **Backend.** `php artisan domain:remove {Product} --dry-run` reads `app/Domain/{Product}/uninstall.json`: every shared-file hit is a `delete`, an `edits` op, or an `ignore` with a reason (`DomainUninstallManifestTest` gates it). Then run it without `--dry-run`.
2. **Registrations leave with the provider.** Contributors, `ai.*`, `storage.*` and the share-URL class register from `{Product}ServiceProvider`, whose `bootstrap/providers.php` line the manifest drops; `config/{product}.php` and its `.env` keys leave, kernel groups stay.
3. **SPA.** `src/features/{product}/`, `src/i18n/features/{product}/`, `tests/{product}*`, one `src/features/registry.ts` line. `npm run check:features` fails on an unregistered directory, a pack missing a locale, or a core file importing `@/features/*`.
4. **Prove it.** `artisan test --filter=DomainUninstallManifest`, `--filter="Core|Navigation|Setting|Menu|Locale"`, SPA `npm run check`.

Adding a product is the mirror image: a domain implementing the contracts and publishing its config keys, a `src/features/{name}/` module with a `messages` loader, one registry line, one pack per locale. No package file is edited on either path.
