# Blog sunset - post-mortem (2026-10-04)

The `Blog` product surface was removed on **2026-10-04** (the code landed 2026-10-02; the removal PRs merged 2026-10-04). This file records why, what left, and how to bring it back.

## Why

The domain had no customer and no path to one.

- **Admin-only authoring with no reader surface.** Blog was a writing tool: posts, categories, tags, featured thumbnails, inline editor images. Every endpoint was `auth:api` and permission-gated, and the SPA was the only consumer — there was no public blog, no RSS, no subscription, nothing a visitor could reach. It wrote content nobody outside the operator seat could read.
- **It carried a full domain's cost for a CMS primitive.** A product domain means a service provider, RBAC permissions, a sidebar menu, seven locale namespaces, a migration, factories, row-action builders and a purifier profile — all of it maintained forever. That is a platform-sized overhead for a feature that never shipped a commercial surface.
- **The workspace already has a writing surface for its own content.** The `docs` repo's Docusaurus site is where published prose lives; Blog was a second, unreachable one.

The platform keeps the nine core domains and the Archivd product with its Microstock subproduct. That is the surface with a customer.

## What was removed

| Surface | Removed |
|---------|---------|
| Backend module | `app/Domain/Blog/**` (Models, Services, `Http/{Controllers,Requests,Resources}`, `Enums/PostStatus.php`), `routes/api/v1/blog.php` and its `require` line, `config/blog.php`, the `blog` profile in `config/purifier.php`, `database/factories/Domain/Blog/PostFactory.php`, `database/migrations/2026_09_16_000001_create_blog_tables.php`, `tests/Domain/Blog/**` |
| Shared backend files | `app/Support/ListActions/Builders/Post{Trashed,}QueryRowActions.php`, `app/Support/EnvironmentVariableCatalog.php`, `config/spa-routes.php`, `database/seeders/MenuSeeder.php` (Blog menu tree), `database/seeders/RoleSeeder.php` (the three `posts` permissions), the `BLOG_MEDIA_DISK` key in the four `.env.*.example` files, and `probe_s3.php` |
| Locale files | the seven `lang/*/api/blog.php` packs and the `posts.*` row-action strings in `lang/*/list_actions.php` |
| SPA | `src/views/blog/**`, `src/components/blog/**` (the Tiptap `RichTextEditor.vue` and `FeaturedImagePicker.vue`), `src/composables/blog/**`, `src/router/routes/blog.ts`, the Blog API types, the five `@tiptap` packages, the `web.blog` subtree in all seven locale packs, its `curation-log.json` entries, the Blog duplicate-English allowlist keys, and `scripts/patch-blog-editor-i18n.cjs` |
| Data (local) | the `blog_*` tables, the `create_blog_tables` migration record, the `blog.*` menu rows, and the three Blog permissions with their role pivots |
| User-facing docs | the `blog.authoring` coverage waiver in the sibling `docs` repo |
| Pakem | `.cursor/rules/blog.mdc`, and the Blog examples in the shared rules |

There was no `app/Domain/Blog/uninstall.json` and no `config/domains.php` registry line — Blog was an admin-only module that predated the manifest convention, so the removal was hand-driven (PR #89 backend, #19 SPA, #3 docs) rather than executed by `domain:remove`.

### The dependency the removal left behind (swept 2026-10-12)

Deleting the `blog` profile from `config/purifier.php` left the package itself in place. `mews/purifier` stayed a `composer.json` requirement, `config/purifier.php` stayed as an application override carrying only its `default` profile, and the provider kept booting on every request through auto-discovery in `composer.lock`'s `extra.laravel.providers`. Its docblock still read *"for remaining application use"* — there was no remaining use. `ezyang/htmlpurifier` came in only transitively, so it stayed too.

Nothing could have caught it. The manifest vocabulary deletes **paths** — `app/Domain/Blog/**`, a route file, a locale pack — and a `composer.json` requirement is not a path, so it had no entry to be listed under. The check that found it is the one the vTual sweep used: grep the whole repo for the package name and ask who still calls it. Nobody did — there is no `*.blade.php` anywhere in this repo and no `Purifier::`/`purify()` call in `app/**`, `routes/**`, `database/**` or `tests/**`. `composer remove --no-scripts mews/purifier` took both packages out, `config/purifier.php` was deleted, and `php artisan package:discover` rebuilt the manifest.

One trap for whoever repeats this: `--no-scripts` skips `post-autoload-dump`, so `bootstrap/cache/packages.php` keeps naming the removed provider. The next boot then fails during provider load, and because `bootstrap/app.php` reports every exception through `ErrorLogRecorder` — whose own dependency is not bound yet at that point — the console shows `ErrorLogRepositoryInterface is not instantiable` instead of the real cause. Clear `bootstrap/cache/packages.php` and `services.php`, or let `package:discover` run. `--no-scripts` is still the right flag, because the default path also fires `@php artisan lang:update`, which can rewrite `lang/**` and silently widen a dependency PR into a locale one.

## Restore point

An annotated tag was cut at `main`'s tip immediately before the removal, in all three repos:

| Repo | Tag |
|------|-----|
| `monolith` | [`blog-sunset-v1`](https://github.com/silverspoonmedia/monolith/releases/tag/blog-sunset-v1) |
| `monolith-vue-workers` | [`blog-sunset-v1`](https://github.com/silverspoonmedia/monolith-vue-workers/releases/tag/blog-sunset-v1) |
| `docs` | [`blog-sunset-v1`](https://github.com/silverspoonmedia/docs/releases/tag/blog-sunset-v1) |

The tag is pushed to `origin` and is the immutable restore point — it points at the same commit as `main` did at sunset, so a restore is a checkout, not a merge. The tag is the **only** restore point: the temporary `rev/sunset-blog` working branches were deleted on 2026-10-08, after the tag was verified reachable at the same commit. Deleting a branch never touches a tag, so nothing about the recovery path changed.

Restore recipe:

```sh
# backend
git checkout blog-sunset-v1 -- .
composer install
php artisan migrate            # create_blog_tables comes back with the tree
php artisan db:seed --class=MenuSeeder
php artisan db:seed --class=RoleSeeder
php artisan permission:cache-reset
php artisan menu:flush-cache
```

The SPA and `docs` halves restore the same way in their own repos (`git checkout blog-sunset-v1 -- .`, then `npm install`). Blog was never a `config/domains.php` entry, so a restore needs no registry edit — the file checkout plus the migrate is the whole job.

The backend recipe also brings `mews/purifier` back on its own: `blog-sunset-v1` predates the 2026-10-12 dependency sweep, so the checkout overwrites `composer.json` and `composer.lock` with the tag's versions and the following `composer install` reinstalls `mews/purifier` and `ezyang/htmlpurifier`. `config/purifier.php` returns with the tree, `blog` profile included. Nothing extra to do.

## What the removal taught

- **A domain can be admin-only and still be a product surface.** Blog was never sold, but it carried the same shape as a sold one (RBAC, menu, locale namespaces, a migration). The manifest convention came later, so the removal had no single reviewed map to read — the cost of an undocumented domain is paid at removal time, not at build time.
- **"No public reader" is a smell, not a feature.** A writing tool whose only consumer is its own admin UI produces content with no destination. If a surface has no reader and no buyer, it is a cost centre with a schema.
- **The tag must be cut before the merge, or it is a guess.** Blog was removed through a PR before the tag convention existed, so the restore point had to be reconstructed from the removal commit's parent. That works, but only because the history is linear; cut the tag in the removal branch and the guess disappears.
- **A removed consumer leaves its dependency behind, and no manifest op covers it.** Blog was the last caller of `mews/purifier`, but the requirement lived in `composer.json` — not in a file the manifest can list. The `uninstall.json` schema has no op for a dependency entry, so this class of residue is invisible by construction and has to be swept by hand: for every package a domain pulled in, ask what else requires it. Do that in the same task as the removal, because a week later the answer takes a whole grep to reconstruct.
- **Locale floors move when a domain's namespace dies.** `locale-glossary-scan.php`'s `MIN_OCCURRENCES` is calibrated to the corpus; the `web.blog.*.slug` SPA keys were the only `Slug` hits in `id`, so the floor dropped 23 → 18 in the same change. Re-measure and lower together — see [reference-localization.md - Curation tooling](../platform/reference-localization.md#curation-tooling).

## Related

- [docs/archive/README.md](README.md) — the archive index
- [vtual-sunset-postmortem.md](vtual-sunset-postmortem.md) — the sibling sunset that followed the manifest convention
- [platform/architecture.md](../platform/architecture.md) — the domain registry after the removal
