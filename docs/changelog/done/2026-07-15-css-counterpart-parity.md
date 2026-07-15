# Changelog entry

## Summary

Two rounds of CSS theming work on the Monolith Blade admin (AdminLTE 4 / Bootstrap 5). Round 1 strengthened light-mode visuals (shadows, borders, login gradient, accent system). Round 2 refactored the entire override layer for counterpart parity — every visual concern that differs between light and dark mode is now token-driven, defined once in `:root` (light) and once in `[data-bs-theme="dark"]`, referenced via `var()` in component rules. No standalone implementations remain. **No API routes, URLs, JSON shapes, or route names were changed** — all modifications are CSS and test files.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-15 |
| Keyword trigger | `upstream documentation session` |
| Related commits / PR | Uncommitted — local working tree changes |

## Delegation

### Monolith (done in this session)

#### Round 1 — Light mode improvements

- Strengthened light-mode shadows (`0.04–0.10` → `0.06–0.14` opacity) so cards have visible lift on white surfaces.
- Strengthened light-mode border (`rgba(15,23,42,.08)` → `.12`) so cards read as distinct surfaces.
- Replaced pale/muddy login gradient (`#eef2ff → #f8fafc → #ecfdf5`) with vivid indigo→slate→violet (`#e0e7ff → #f1f5f9 → #ede9fe`).
- Added accent system: `--monolith-accent` (`#6366f1` light / `#818cf8` dark), `--monolith-accent-soft`, `--monolith-accent-soft-hover`.
- Accent-tinted interactive hover states (sidebar nav-link, table rows, dropdown items, list-group items).
- Sidebar active link gets accent background + left indicator bar (`inset 2px 0 0 var(--monolith-accent)`).
- Card hover lifts slightly (`translateY(-1px)`) for tactile feedback.
- Applied `--monolith-text-strong` to `.card-title` for sharper headings.

#### Round 2 — Counterpart parity refactor

Eliminated 6 categories of one-directional implementations:

1. **Hardcoded focus hex** — `#6366f1` (light indigo) was hardcoded in focus rings in both modes. Replaced with `var(--monolith-focus-color)` and `var(--monolith-focus-ring)` so dark mode gets `#818cf8` automatically. Affected: `.login-box .form-control:focus`, `.form-control:focus`, `.form-select:focus`, `.breadcrumb-item a:hover`, Select2 highlighted option.
2. **Dark-only text normalization block** — dark mode had explicit text colors on `body`, `.text-body`, `.card-body`, `dt`/`dd`, `.form-check-label`, `hr`, `code`, `.text-muted` with no light counterpart. Added a light-mode counterpart block using `var(--monolith-text-body)` / `var(--monolith-text-code)`. Both modes now define the same set of concerns.
3. **Login gradient hardcoded hex** — replaced with `var(--monolith-gradient-start/mid/end)` tokens in both light and dark `.login-page` rules.
4. **Payment CSS hardcoded hex** — `channels-admin.css` and `order-invoice-show.css` had light-mode hex with separate dark-mode rgba overrides and no shared tokens. Added `--pc-kind-*`, `--ois-kind-*`, `--pc-status-*`, `--ois-status-*`, `--pc-intro-gradient-*`, `--ois-hero-gradient-*` tokens defined in both light and dark blocks. Removed the now-redundant dark-mode override blocks.
5. **`--monolith-text-strong` underused** — only applied to `.card-title`. Now also on `.app-content-header h3` and `.login-logo`, reducing the need for per-element dark-mode color overrides.
6. **Code color dark-only** — `#fba74d` was dark-only. Added `var(--monolith-text-code)` token (`#c2410c` light / `#fba74d` dark) and a light-mode `code` rule.

#### New tokens added (both `:root` + `[data-bs-theme="dark"]`)

`--monolith-text-body`, `--monolith-text-code`, `--monolith-focus-color`, `--monolith-focus-ring`, `--monolith-gradient-start`, `--monolith-gradient-mid`, `--monolith-gradient-end`, `--monolith-status-secondary-{bg,text}`, `--monolith-status-warning-{bg,text}`, `--monolith-status-success-{bg,text}`, `--monolith-status-danger-{bg,text}`, `--monolith-kind-qris-{bg,text}`, `--monolith-kind-va-{bg,text}`, `--monolith-kind-ewallet-{bg,text}`.

Same pattern mirrored as `--pc-*` (channels-admin.css) and `--ois-*` (order-invoice-show.css) in payment CSS.

#### Tests

Created `tests/Feature/Http/Controllers/Web/ThemeOverrideCssTest.php` — 23 tests / 170 assertions. Guards:

- Token presence (accent, shadow, border, counterpart tokens in both `:root` and `[data-bs-theme="dark"]`)
- No hardcoded `#6366f1` or `#e2e8f0` in component rules (only token definitions allowed)
- Login gradient uses `var()` not hex
- Light-mode text normalization block exists
- Focus rings use tokens
- Payment CSS kind/status/gradient tokens exist in both modes
- Login page renders with CSS asset link and theme toggle script

Full suite: 590 tests, 589 passed, 1 skipped (pre-existing), 2 risky (pre-existing). Vite build succeeded.

#### Documentation updated

| Document | Change |
|----------|--------|
| [docs/web-frontend.md](../../web-frontend.md) | Design tokens table expanded (21 new tokens); added "Counterpart parity principle" section; updated dark mode / override layer sections; ThemeOverrideCssTest in test reference table |

### Monolith Gateway

- [x] None required — CSS/test-only changes, no HTTP contract change.

### Monolith Frontend

- [x] None required — CSS/test-only changes, no SPA contract change. (Related i18n work for `ui.ajax` remains in [pending/2026-07-14-blade-dark-mode-theming.md](../pending/2026-07-14-blade-dark-mode-theming.md).)

## API contract

N/A — no API, URL, or JSON changes.

## Supersedes

- 

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| No | Blade CSS and tests only |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `composer test` |
| Gateway | n/a |
| Frontend | n/a |

## Status

| Field | Value |
|-------|-------|
| Status | `done` |
| Synced | 2026-07-15 (Monolith-only; no Gateway/Frontend work) |
