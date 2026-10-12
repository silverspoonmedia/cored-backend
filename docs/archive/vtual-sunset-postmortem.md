# vTual sunset - post-mortem (2026-10-07)

The `Vtual` product domain was removed on **2026-10-07**. This file records why, what left, and how to bring it back. It is the only surviving archive entry of the vTual set — the three assessment files that used to sit beside it (`vtual-legacy-audit.md`, `vtual-vue-island-assessment.md`, `vtual-youtube-stability-assessment-2026-08-07.md`) were deleted on 2026-10-08, and the restore point below is the only copy of that history.

## Why

The domain could not be sustained.

- **Data volume with a freshness obligation.** vTual mirrored YouTube and Twitch: channels, contents, live sessions, CCV and metric snapshots, claim ledgers, management rosters. Every mirrored row carries a staleness cost - a feed poll, a pool flush, a quota budget, a retry ladder - and the corpus grew faster than the maintenance capacity behind it. A mirror that is allowed to go stale is worse than no mirror.
- **Solo maintenance, no ceiling.** The ingestion surface alone was 16 console commands, two webhook families (WebSub, EventSub), a platform-credential failover chain, and an AI classification audit - one person's ongoing attention, permanently, for a domain with no paying demand behind it.
- **No commercial case.** Nothing in the product pipeline pointed at it: no SKU, no checkout path, no entitlement anyone asked for. It consumed core capacity (Identity, RBAC, Navigation, ActivityLog, ErrorLog, Ai) and returned nothing.

The platform keeps the nine core domains and the Archivd product with its Microstock subproduct. That is the surface with a customer.

## What was removed

| Surface | Removed |
|---------|---------|
| Backend module | `app/Domain/Vtual/**`, `app/Providers/Domain/VtualServiceProvider.php`, `routes/api/v1/vtual*.php`, `app/Console/Commands/Vtual/**`, `database/seeders/VtualRoleSeeder.php`, `config/vtual.php`, `tests/Domain/Vtual/**`, `tests/Fixtures/vtual/**` |
| Shared backend files | `config/{domains,products,spa-routes,settings-local,ai,encryption,navigation}.php`, `SettingRegistry`, `SystemSettingRepository`, `MenuSeeder`, `DomainAccessSeeder`, `RoleSeeder`, `RequireSpaRequestHeader`, `SpaPublicUrl`, the eight `app/Support/ListActions/Builders/*QueryRowActions.php`, `routes/{api/v1,channels,console}.php`, the locale tooling (`scripts/locale-dynamic-key-check.php`, `scripts/lib/locale-latin-allowlist.php`, `scripts/locale-glossary-scan.php` floors) and the cross-domain tests |
| Locale files | seven `lang/*/api/vtual.php`, seven `lang/*/web/vtual-vtuber.php`, seven `lang/*/web/vtual-orphans.php`; the `vtual.*` blocks and `list_actions` claim strings removed from the surviving files |
| SPA | `src/views/vtual/**`, `src/components/vtual/**`, `src/composables/vtual/**`, `src/router/routes/vtual.ts`, `src/valkivid3/**` (the frozen vTual landing template), the vTual utilities in `src/utils/vtual*.ts`, the `// --- Vtual ---` block in `src/api/types.ts`, the seven locale blocks, `curation-log.json` entries, and the SPA tests |
| Data (local) | the `vtual_*` tables, the recorded RBAC (14 permissions, 5 roles), the `product_activations` / `product_domain_visibilities` / `product_analytics` rows, and the `vtual.*` menu rows |
| User-facing docs | `docs/vtual/**` in the sibling `docs` repo, its coverage entries, navbar and footer links |
| Pakem | `.cursor/rules/vtual.mdc`, `.cursor/rules/vtual-content.mdc`, the SPA `valkivid3.mdc`, and the vTual examples in the shared rules |

`app/Domain/Vtual/uninstall.json` was the driver for most of it: `domain:remove Vtual --purge-data --force` executed the manifest, and the manifest's `ignore` list was the reviewed worklist for the hand edits the closed op vocabulary cannot express. The manifest died with the tree, which is why this file and the tag carry the record instead.

## Residue sweep (2026-10-12)

The manifest-driven removal was complete, but four classes of residue outlived it. None of them was visible to a test or a guard at the time:

| Residue | Where | Why nothing caught it |
|---------|-------|----------------------|
| Dead ownership-verify helpers | `DiscordConnectionsClient::ownershipEligible()` and `externalLinkCandidates()`, `UserOAuthAccountService::connectionsForOwnershipVerify()` and `hasDiscordConnection()`, plus their five unit tests | vTual was the only caller. The methods were public, typed, and still green — no coverage or static check had a reason to look at them. |
| Orphaned curation flags | 132 `// curated at` lines in `lang/{id,ja,ko,zh_CN,ar,ru}/web/settings.php` and `lang/{ar,id,ja,ko,zh_CN}/api/notifications.php` | The flag is a comment, not a key. Parity compares keys, the usage test walks call sites, and the prose scan strips comments before it reads values, so all three skipped it. |
| Stacked curation flags | 25 more `// curated at` lines in `lang/{id,ja,ko,ru}/web/settings.php`, `lang/zh_CN/web/settings.php`, `lang/zh_CN/list_actions.php`, and `lang/id/api/microstock.php` | The first version of the flag guard skipped a run of flags and judged the run by its last member, which did still sit above a key. A stack is only ever left behind by a deleted key, so that tolerance was itself the hole. |
| Allowlist tokens only vTual needed | `Kick`, `WebSub`, `Helix`, `YouTube`, `Twitch` in `scripts/lib/locale-latin-allowlist.php` | The prose scan reports a token it is *not* told to accept, so a stale acceptance is silent by construction. `YouTube`/`Twitch` had zero occurrences left to accept — the domain mirrored them, so they were its vocabulary, not the platform's. |

Two guards came out of this: `scripts/locale-curation-flag-scan.php` — a flag whose next line is not the key it belongs to is a finding, whether that next line is another flag or the end of the file — and the stack-collapsing writer in `scripts/apply-locale-translations.php`, which now replaces the whole run of flags above a key instead of only the line directly above it. The scan is sibling-free, so it is gated in CI rather than only on a machine with both checkouts. The allowlist tokens were dropped from `scripts/lib/locale-latin-allowlist.php`; the dead helpers needed nothing new, because asking "who else calls this?" was the whole fix.

The stacked flags were found by the **second** pass, after the first had already reported `0`. That is the shape of this residue in general: the guard was built from the same removal that produced the leftovers, so it inherited one of that removal's blind spots, and re-reading the files was never going to show it. Widening the guard is what found them, and the corpus change it then needed was comment-only. When a removal writes its own detector, ask which of the removal's own assumptions the detector just adopted.

## Restore point

An annotated tag was cut **before** anything was deleted, in all three repos:

| Repo | Tag |
|------|-----|
| `monolith` | [`vtual-sunset-v1`](https://github.com/silverspoonmedia/monolith/releases/tag/vtual-sunset-v1) |
| `monolith-vue-workers` | [`vtual-sunset-v1`](https://github.com/silverspoonmedia/monolith-vue-workers/releases/tag/vtual-sunset-v1) |
| `docs` | [`vtual-sunset-v1`](https://github.com/silverspoonmedia/docs/releases/tag/vtual-sunset-v1) |

The tag is pushed to `origin` and is the immutable restore point - it points at the same commit as `main` did at sunset, so a restore is a checkout, not a merge. The tag also carries the only copy of the three deleted assessment files. The tag is the **only** restore point: the temporary `rev/sunset-vtual` working branches were deleted on 2026-10-08, after the tag was verified reachable at the same commit. Deleting a branch never touches a tag, so nothing about the recovery path changed.

Restore recipe:

```sh
# backend
git checkout vtual-sunset-v1 -- .
composer install
php artisan migrate            # the 33 migrations come back with the tree
php artisan db:seed --class=VtualRoleSeeder
php artisan db:seed --class=MenuSeeder
php artisan permission:cache-reset
```

The SPA and `docs` halves restore the same way in their own repos. Re-lock the domain afterwards (`config/domains.php` -> `'Vtual' => ['kind' => 'product', 'locked' => true]`) - the tag predates the unlock, but a restore that leaves it removable is a foot-gun.

## What the removal taught

- **The manifest is the removal plan, not the whole job.** `--dry-run` reported 0 unresolved, yet 73 `ignore` entries were still hand edits. A removal task is sized by the `ignore` list, not by the manifest's `delete`/`edits` counts. Budget for the hand edits first.
- **A closed vocabulary hides copy.** `log_name: vtual` in the activity-log rule, `MenuLayout::VTUAL`, `vtual.*` route names, `settings.vtual` - every one was a string in a fixed set that no scanner can distinguish from a value. They surfaced only by reading each owning rule against the surviving code.
- **Deleting locale files moves the glossary floors.** `locale-glossary-scan.php`'s `MIN_OCCURRENCES` is calibrated to the corpus, and `lang/*/api/vtual.php` carried the bulk of several `ru`, `id`, `ja`, `zh_CN` and `ar` terms. Removing the domain without re-measuring turned the guard red for reasons unrelated to translation quality. Re-measure and lower in the same change - see [reference-localization.md - Curation tooling](../platform/reference-localization.md#curation-tooling).
- **Sweep the archive last, and reword rather than delete.** The archived assessments carried prose that linked into `domains/vtual/**`; `docs-guard.php` resolves link paths, so an archive file that links a deleted tree fails the build even though the prose is fine. Reword the survivors, and delete the entries that are nothing but that dead link - their history lives in the tag.
- **The manifest's residue is not the whole residue.** The `ignore` list covers the *files* the manifest knows about; what survives a removal is the code the domain was the last caller of, the comments that outlive their keys, and the entries that taught a guard to accept its brand names. The three questions that find them are not manifest questions: who else calls this, what did its keys leave behind, and what did we teach a guard to accept for it? The residue sweep a week later found all four classes — and the fourth only after the guard written for the second was widened, which is the reminder that a detector authored by the removal can inherit the removal's assumptions.
- **A domain that mirrors an external platform carries a permanent cost.** Ingestion, freshness, quota, retries, webhook leases, credential failover: none of it ends when the feature ships. Price that before the second platform is added, not after.

## Related

- [docs/archive/README.md](README.md) - the archive index
- [platform/architecture.md](../platform/architecture.md) - the domain registry after the removal
- [platform/reference-commands.md](../platform/reference-commands.md) - the surviving console command list
