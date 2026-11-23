---
name: domain-remove
description: Removes a product domain (its PHP module, routes, tests, shared-file edits, data, SPA screens and docs) with the `domain:remove` command and the domain's `uninstall.json` manifest. Use when the user asks to remove, delete or uninstall a product domain, mentions `domain:remove`, `--draft-manifest`, or a domain `uninstall.json`.
---

# Removing a product domain

A product domain is removed through its own `uninstall.json`, never by hunting
references by hand. The manifest is the reviewed map; `domain:remove` is the
executor. Your job is to leave both in a state the next removal can trust.

Schema, op vocabulary, sibling-repo rules: [docs/platform/reference-architecture.md](../../../docs/platform/reference-architecture.md#domain-uninstall-manifest).
Do not restate them here — that file is the owner.

## Procedure

1. **Confirm the domain is removable.** `php artisan domain:list` — `kind` must
   be `product`, the config entry must not be locked, and **no registered
   subproduct may point at it** (`Parent` column). Unlocking is a manual
   edit of `config/domains.php` (`'locked' => false`); never work around it.
   Core domains are refused by `PLATFORM_CORE` and are out of scope.
   A subproduct (`'parent' => '{Name}'`) is a full product with its own
   manifest — remove the child first; `domain:remove` refuses the parent until
   the last registered child is gone.

2. **Preview.** `php artisan domain:remove {Name} --dry-run`.
   Read three things: the plan counts, the `unresolved` list, and the
   "references not covered by the manifest" list. Dry-run is read-only and is
   allowed even while the domain is locked.

3. **Finish the manifest before removing anything.**
   - No `uninstall.json` yet → `php artisan domain:remove {Name} --draft-manifest`,
     then review the draft.
   - Unlisted references → add an `edits[]` op, add a `delete` path, or add an
     `ignore` entry whose reason says why the reference survives **or** spells
     out the hand edit the closed op vocabulary cannot express.
   - `unresolved` entries → classify them the same way. Emptying it is the point
     of this step; `domain:remove` refuses to run while it is not empty.
   - Repeat until `--dry-run` reports no unlisted references and no unresolved
     entries.
   - **An op must still remove something after the product's own files are
     gone.** Write it against the entry that survives next to them — the kernel's
     opt-in hook line, the provider's registration — not against the line that
     used to hold product code. `remove_block` is a selector: `begin` removes the
     container its anchor opens, otherwise the anchored entry alone, and
     `begins` removes several anchored entries as one removal. An op credited
     `0` in the plan is dead: anchor it elsewhere or drop the file into `ignore`
     with the reason it leaves with a sibling.

4. **Keep the map true while you work.** Any file a domain touches outside
   `app/Domain/{Name}/` is recorded in `uninstall.json` in the same task that
   created it. `DomainUninstallManifestTest` fails otherwise.

5. **Remove.** `php artisan domain:remove {Name} --force`.
   `--force` is required because the run is non-interactive; it does not skip
   the `unresolved` check. Deletes, shared-file edits and the structural
   rewrites (`config/domains.php`, `bootstrap/providers.php`, the route loaders,
   `DatabaseSeeder`) all run here. Migrations stay.

6. **Purge data only if the data really goes.**
   `php artisan domain:remove {Name} --purge-data --force` drops the recorded
   tables, deletes the recorded rows (product rows, menus, service catalog) and
   RBAC roles/permissions, then deletes the recorded migrations. It is refused
   unless `DestructiveDatabaseCommandGuard` allows it
   (`ALLOW_DESTRUCTIVE_DB=true` outside sqlite/local). Never set that flag just
   to get past the guard.

7. **Finish the cross-repo half.** The manifest's `spa` and `docs` sections are
   applied only when the sibling checkouts are present; a missing sibling is
   reported as *unverifiable*, not as clean. In the SPA repo, remove the recorded
   views/composables/router entries and their locale keys, then run
   `npm run check:i18n`. Customer-visible pages in the `docs` repo change in the
   same task.

8. **Gates.** Then `php artisan test --filter=Domain`,
   `php artisan test --filter=Documentation`, `php scripts/docs-guard.php`, and
   the locale gates when a language pack moved.

9. **PR.** One branch per repo, PR to `main`, left open for review — the user
   merges. Commit per logical unit (manifest, code, data, docs).

## Notes

- Worklist reasons are not a licence: `unresolved` is empty for a domain whose
  manifest is done, and `ignore` always carries a reason.
- The manifest dies with the domain, which is why it lives inside
  `app/Domain/{Name}/`.
- `notes[]` holds the work the ops cannot express (queue lanes, external
  subscriptions, docblock rewordings). Read it before calling the removal done.
