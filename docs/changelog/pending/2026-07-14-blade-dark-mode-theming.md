# Changelog entry

## Summary

Two sessions of UI/UX improvements to the Monolith Blade admin (AdminLTE 4 / Bootstrap 5). The work created a soft visual override layer, fixed widespread dark mode contrast issues, and corrected invalid Bootstrap badge syntax. **No API routes, URLs, JSON shapes, or route names were changed** — all modifications are Blade markup, CSS, and build config.

The only remaining downstream action: the Frontend Vue SPA is missing the `ui.ajax.*` i18n namespace that Monolith Blade now references (Frontend already has `ui.password.show`/`hide`). The Frontend should add the `ui.ajax` keys to `src/i18n/{en,id,ja}.json` to maintain parity with Monolith `lang/{locale}/ui.php`.

## Session metadata

| Item | Detail |
|------|--------|
| Date | 2026-07-14 |
| Keyword trigger | mid-task adaptation / session work |
| Related commits / PR | Uncommitted — local working tree changes |

## Delegation

### Monolith (done in this session)

#### Session 1 — Soft override layer + initial dark mode fixes

- Created `resources/css/monolith-overrides.css` — global CSS override layer with design tokens (`--monolith-*`), soft shadows, rounded corners, theme-aware card/layout/auth/sidebar/form/datatable/badge/alert/dropdown/modal styles. Pure CSS (no Tailwind) to avoid Bootstrap conflicts. Loaded via `@vite` in `assets-head.blade.php` on every page.
- Wired `monolith-overrides.css` into `vite.config.js` Vite input array.
- Fixed Select2 dark mode (selection, rendered text, placeholder, multi-choice chips, dropdown, results, search field).
- Fixed activity-logs index badge (`text-muted` conflicting with `text-bg-light` in dark mode).
- Fixed activity-logs show `<pre>` (`bg-light` → `bg-body-tertiary`).
- Fixed payment channels dark mode (Select2, custom `pc-*` elements).
- Removed hardcoded `data-bs-theme="dark"` from sidebar — sidebar now follows user theme choice.
- Removed `bg-dark-subtle` from sidebar search input.
- Fixed 2 hardcoded Indonesian strings → translation keys:
  - `alert.blade.php`: hardcoded `Periksa kembali isian berikut:` → `__('ui.ajax.validation_failed')`
  - `input.blade.php`: hardcoded `Tampilkan kata sandi` aria-label → `__('ui.password.show')`
- Updated `resources/css/payments/order-invoice-show.css` — all hardcoded hex colors replaced with `--ois-*` custom properties + `[data-bs-theme="dark"] .ois` dark overrides.
- Updated `resources/css/payments/channels-admin.css` — added `--pc-*` dark mode tokens + explicit text color overrides for `pc-*` elements.

#### Session 2 — CSS cascade bug fix + contrast audit

- **Critical bug fix:** `channels-admin.css` and `order-invoice-show.css` had dark mode custom properties scoped to global `[data-bs-theme="dark"]` while light mode tokens were scoped to `.pc-admin` / `.ois`. Due to CSS custom property cascade rules (element-level declaration beats ancestor-level), dark tokens never applied. Fixed by scoping dark overrides to `[data-bs-theme="dark"] .pc-admin` and `[data-bs-theme="dark"] .ois`.
- Migrated 8 legacy `bg-*` badges → `text-bg-*` in 5 approval view files (Bootstrap 5.3 `text-bg-*` auto-contrasts text+background).
- Fixed invalid `badge-{status}` syntax in `approvals/show.blade.php` (was `badge badge-warning` — not valid Bootstrap 5.3, badge got no styling).
- Wrapped QR code `bg-white` with theme-aware container (`bg-body-tertiary border`) in `profile/two-factor.blade.php` — inner `bg-white` preserved for QR scannability.
- Made sidebar scrollbar theme-aware in `assets-scripts.blade.php` — was hardcoded `os-theme-light`, now detects `data-bs-theme` and switches `os-theme-light`/`os-theme-dark`, updates on theme toggle.

#### Changed files

| File | Change type |
|------|-------------|
| `resources/css/monolith-overrides.css` | **New** — global CSS override layer |
| `resources/css/app.css` | Modified (Tailwind imports restructured) |
| `resources/css/payments/channels-admin.css` | Modified (dark mode tokens, cascade fix, text colors) |
| `resources/css/payments/order-invoice-show.css` | Modified (dark mode tokens, cascade fix) |
| `resources/views/adminlte4/components/partials/assets-head.blade.php` | Modified (load `monolith-overrides.css` via `@vite`) |
| `resources/views/adminlte4/components/partials/assets-scripts.blade.php` | Modified (theme-aware scrollbar) |
| `resources/views/adminlte4/components/partials/sidebar.blade.php` | Modified (removed hardcoded `data-bs-theme="dark"`) |
| `resources/views/adminlte4/components/partials/sidebar-nav-search.blade.php` | Modified (removed `bg-dark-subtle`) |
| `resources/views/adminlte4/components/alert.blade.php` | Modified (i18n fix) |
| `resources/views/adminlte4/components/form/input.blade.php` | Modified (i18n fix) |
| `resources/views/adminlte4/activity-logs/index.blade.php` | Modified (badge dark mode fix) |
| `resources/views/adminlte4/activity-logs/show.blade.php` | Modified (`bg-light` → `bg-body-tertiary`) |
| `resources/views/adminlte4/approvals/show.blade.php` | Modified (`badge-{status}` → `text-bg-{status}`, `bg-*` → `text-bg-*`) |
| `resources/views/adminlte4/approvals/my-pending.blade.php` | Modified (`bg-primary` → `text-bg-primary`) |
| `resources/views/adminlte4/admin/approval-workflows/show.blade.php` | Modified (`bg-*` → `text-bg-*`) |
| `resources/views/adminlte4/admin/approval-workflows/index.blade.php` | Modified (`bg-*` → `text-bg-*`) |
| `resources/views/adminlte4/profile/two-factor.blade.php` | Modified (QR code theme-aware wrapper) |
| `resources/views/adminlte4/payment-channels/index.blade.php` | Modified (dark mode badge fixes) |
| `vite.config.js` | Modified (added `monolith-overrides.css` to input) |
| `docs/web-frontend.md` | Modified (documentation update) |

### Monolith Gateway

- [x] None required. Gateway is a pure API proxy with no Blade views, CSS assets, or i18n files. All four route families (`payment-channels`, `activity-logs`, `approvals`, `admin.approval-workflows`) are already mapped in `config/vue-routes.php` and remain unchanged.

### Monolith Frontend

- [ ] Add `ui.ajax` i18n namespace to `src/i18n/en.json`, `src/i18n/id.json`, `src/i18n/ja.json`. The Frontend currently has only `ui.password.*` under the `ui` namespace. Monolith `lang/{locale}/ui.php` defines the full `ajax` sub-array:

  **`en.json` — add under `"ui"`:**
  ```json
  "ajax": {
    "saved": "Saved successfully.",
    "validation_failed": "Please check your input.",
    "request_failed": "The request could not be processed.",
    "server_unreachable": "Could not reach the server. Check your connection.",
    "datatable_load_failed": "Could not load the table data. Please try again."
  }
  ```

  **`id.json` — add under `"ui"`:**
  ```json
  "ajax": {
    "saved": "Berhasil disimpan.",
    "validation_failed": "Periksa kembali isian Anda.",
    "request_failed": "Permintaan gagal diproses.",
    "server_unreachable": "Tidak dapat menghubungi server. Periksa koneksi Anda.",
    "datatable_load_failed": "Tidak dapat memuat data tabel. Silakan coba lagi."
  }
  ```

  **`ja.json` — add under `"ui"`:**
  ```json
  "ajax": {
    "saved": "保存しました。",
    "validation_failed": "入力内容を確認してください。",
    "request_failed": "リクエストを処理できませんでした。",
    "server_unreachable": "サーバーに接続できませんでした。接続を確認してください。",
    "datatable_load_failed": "テーブルデータを読み込めませんでした。もう一度お試しください。"
  }
  ```

- [ ] Optionally: audit Frontend components for any hardcoded Bootstrap `bg-*` badge classes and migrate to `text-bg-*` for consistency with Monolith Blade admin (Bootstrap 5.3 best practice, auto-contrasts text+background in both light and dark mode). This is not a contract requirement — just visual parity.

- [ ] Optionally: if the Frontend has any hardcoded `bg-white` or `bg-light` utility classes, replace with `bg-body-tertiary` or `bg-body-secondary` for dark mode awareness. Again, not required — Frontend already uses Bootstrap-native `data-bs-theme` dark mode via `useTheme.ts` + `ThemeToggle.vue`.

## API contract

N/A — no API, URL, or JSON changes. All changes are Blade/CSS/build config.

## Supersedes

- 

## Breaking change?

| Breaking? | Mitigation |
|-----------|------------|
| No | Blade-only changes + i18n key addition. No API/URL/route contract change. Frontend i18n addition is additive (new keys, no existing keys modified). |

## Test plan

| Repo | Command |
|------|---------|
| Monolith | `composer test` |
| Gateway | `php artisan test` (no changes expected — regression only) |
| Frontend | `npm run test:unit -- --run`; `npm run build` |

**Manual checks (local Laragon + Vite):**

1. Frontend: trigger a form validation error (e.g., submit a form with empty required fields) → verify the error toast/alert shows the translated string from `ui.ajax.validation_failed` instead of the raw key.
2. Frontend: switch all 3 locales (en, id, ja) → verify `ui.ajax.*` strings render correctly in each.
3. Monolith Blade: toggle dark mode on payment-channels, activity-logs, approvals, profile/two-factor pages → verify no white-on-white or dark-on-dark contrast issues remain.
4. Monolith Blade: toggle dark mode → verify sidebar scrollbar switches theme (light scrollbar in light mode, dark scrollbar in dark mode).
5. Monolith Blade: view approval detail page → verify status badges have correct background + auto-contrasted text.

## Status

| Field | Value |
|-------|-------|
| Status | `pending` |
| Synced | — |
