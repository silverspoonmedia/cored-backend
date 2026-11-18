# Packages published as one-way mirrors — `silverspoonmedia/*`

> 2026-11-18. The five core domain packages moved vendor prefix from `silverspoon/*` to `silverspoonmedia/*` and gained a read-only mirror repo per package. The monolith stays the source; a mirror is a derivative produced by a script. Live contract: [integration/core-packages.md](../integration/core-packages.md#distribution).

## Why the rename

`silverspoonmedia` is the org that already owned a Composer-installed package (`silverspoonmedia/pdf-tools`, a VCS repository in the root manifest). A second vendor prefix on the same org was a naming accident of the POC, not a decision: two prefixes mean two `repositories` blocks, two token scopes to explain, and a `vendor/` tree that reads as two vendors. One prefix, one org, one rule.

The rename is mechanical — 61 files, zero behavior change — because the package name never appears in PHP: the namespace is `App\Domain\{Domain}\*` and stays that way. The name appears in the root `require`, in `config/domains.php`, in `PackageOverrideTest`'s assertions, in comments, in rules and in docs. Route count is the proof it moved nothing: 204 before, 204 after.

## Why mirrors, and why one-way

The goal recorded in [core-packages.md](../integration/core-packages.md) is "a client project installs `silverspoonmedia/navigation` as a real dependency without forking this repo". A path repository cannot deliver that: it resolves on a machine that already has the monolith checkout, which a client does not.

Three models were on the table:

| Model | Source of truth | Cost |
|---|---|---|
| Keep path only | monolith | No external consumer can install at all |
| Package repos become source | 5 repos | Loses the single `php artisan test` run, the shared `composer.lock`, and one `git log`; every core change becomes a 5-repo dance |
| **One-way mirror (chosen)** | monolith | A published tag is a derivative; a change still starts here |

The mirror wins because the coupling between these packages and the host is real: they consume `App\Support\*` kernel contracts that live in this repo, they are tested by this repo's `Packages` suite against this repo's factories, and `config/domains.php` names their route files. Splitting the source would have made the *host* the odd one out.

The cost is accepted explicitly: **a mirror cannot receive a PR.** If two-way contribution ever becomes a requirement, the model has to flip to package-repos-as-source, and that is a different project.

## Mechanics worth remembering

- **`git subtree split --prefix=packages/{slug}`** replays only that subtree's history, so the mirror opens with the `git mv` that created the package rather than one squashed import. This is why the extraction PR merges with a **merge commit**, not a squash.
- **`version` is dropped in the mirror, not in the monolith.** A path repository needs an explicit `version` or `composer update` fails against `minimum-stability: stable` on a feature branch. A tagged VCS repository must *not* carry one, or every tag reports `1.0.0` and `^1.0` cannot distinguish releases. The publish script strips it in the mirror commit before tagging.
- **`.gitattributes` `export-ignore`** keeps `tests/` and `.github/` out of the downloaded tag. Without it a host pulls 300 files for billing instead of the ~180 that are source.
- **Windows junctions are still how the host sees the packages.** `"symlink": true` over a `path` repository produces a directory junction; the rename made Composer create `vendor/silverspoonmedia/*` and drop `vendor/silverspoon/*` on its own, so no manual cleanup was needed.
- **`composer update <packages> --no-scripts`** is not optional here: `post-update-cmd` runs `vendor:publish` + `lang:update`, which once rewrote 37 non-package files under `lang/`.

## What did not change

Product domains (`Archivd`, `Microstock`) stay in `app/Domain/*`, locked, with their `uninstall.json` untouched. The SPA repo and the user-facing `docs` site were not modified — the label `silverspoon/legal` in `docs/docs-coverage.json` is an owning-area label, not a Composer package name.
