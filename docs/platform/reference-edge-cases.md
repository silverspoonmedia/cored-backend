# Edge cases — reference

The behaviours that only matter when a run goes wrong: why a gate reports something
that is not a defect, and what to do when it is. Usage and the gate list are in the
brief ([tooling.md](tooling.md)); this page owns the *why*.

## A green run is not `passed == tests`

The suite ends with skipped and risky reports **by design**. Reading them as failures
is the single most common false alarm.

| Report | Count when both repos are checked out | Cause | Action |
|---|---|---|---|
| `skipped` OCI KMS integration | 1 | Needs a live OCI vault; CI has no credentials | none — stays skipped |
| `skipped` cross-repo guards | 7 | The guard needs the sibling SPA checkout (locale pack parity, prose scan, docs anchor parity, …) | run from the SPA workspace root to exercise them |
| `risky` | 1 | A test writes to stdout on purpose (Artisan output contract) | none — the assertion covers it |

A run is green when `failed: 0` and `errors: 0`. A skip that *disappears* is the
suspicious change, not the skip itself.

## One suite at a time

`tests/bootstrap.php` takes an advisory lock on `bootstrap/cache/.phpunit.lock` and
refuses to start a second concurrent run. Two runs share `bootstrap/cache` and the
temporary domain fixtures, so the second one reports phantom failures in files the
change never touched. When a run refuses to start, an earlier run is still alive —
wait, or kill it, never delete the lock file by hand.

## A product-free tree

The boilerplate ships ten core domains and zero products. Three contracts follow from
that and each one fails in a way that names a *core* file:

- **`domain:list` shows no product row.** A test that asserts a product's `kind`,
  `locked`, or `parent` has no subject and must be deleted, not re-pointed at a core
  domain. The axes themselves stay covered by `DomainCommandsTest`'s synthetic
  registry fixture.
- **A seam returns its empty state.** `FulfillmentRegistry`, `AiUsageLedger`, and the
  product-activation middleware all resolve a contract that a product binds. With no
  product installed the contract is unbound and the service returns an empty
  envelope or a no-op — never a container exception. A `BindingResolutionException`
  from one of these is a seam defect, not a missing install step.
- **A list-action builder for a product model is gone.** A builder whose query row
  type is a product model cannot survive the product; the directory leaves with the
  domain. `PresentationResourceTest` and `docs/platform/list-display-modes.md` are the
  two places that traditionally still name it.

## Locale guards after a removal

Removing a domain removes its locale namespace (`lang/{locale}/web/{domain}.php`)
and every key that only it rendered. Two guards then report findings that are **not**
translation defects:

- **`locale-curation-flag-scan`** counts `// curated at …` flags whose key no longer
  exists. Every flag on a deleted key is orphaned; delete the flag line in the same
  wave that deletes the key.
- **`locale-glossary-scan`** keeps a *floor* per settled term — the number of times
  the term appears in the locale's prose. A term whose only consumer left the tree
  drops below its floor even though every remaining occurrence is correct. Re-measure
  the floor from the surviving tree (`php scripts/locale-glossary-scan.php
  --report-floors`), never lower it by hand to make the gate green.

## Docs guard after a removal

`docs-guard.php` validates four things at once and a removal breaks all four:

| Finding | Why it appears | Fix |
|---|---|---|
| a link to `domains/{product}/…` no longer resolves | the page left with the domain | delete the link, or re-point it at the core owner of the behaviour |
| a `#anchor` no longer exists | the heading was product-only | re-point at the surviving heading |
| a rule `globs:` matches no file | `vendor/silverspoonmedia/**` in the `globs:` is a placeholder copy-pasted from the living line, where the same rule points at `app/Domain/**` or the package path | point the glob at the write surface this repo actually has (`vendor/silverspoonmedia/**` is fine for a whole-tree rule; a per-domain rule names the package dir) or drop the dead alternative |
| a doc-budget sample path is missing | the sample was a product doc | point the sample at a surviving doc of the same kind |

The doc-size budgets are stated twice on purpose — in `scripts/lib/docs-budget-support.php`
and in `.cursor/rules/docs-pakem.mdc` — and `DocsBudgetGuardTest` fails when the two
disagree. Change both, or neither.
