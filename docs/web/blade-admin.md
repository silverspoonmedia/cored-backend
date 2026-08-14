# Web frontend (Blade + AdminLTE 4)

API-first Blade admin: every web controller forwards to `/api/v1/*`. **No business logic, Form Requests, or `$this->authorize()` in the web layer.**

This Blade layer stays in-repo as the current frontend and the **reference** until the Vue-Workers port succeeds — do not remove it. Monolith-Vue-Workers is the **leading SPA frontend** (separate project, same `/api/v1/*` API, Bearer token) — see [integration/vue-workers-spa.md](../integration/vue-workers-spa.md).

```
Browser → Web controller → ApiClient → /api/v1/* → Services / Policies
                ↑ JSON envelope → Blade / redirect / JsonResponse
```

Auth/RBAC ownership: [identity](../domains/identity/README.md). Localization task rule: [platform/localization](../platform/localization.md).

## Auth: JWT in HttpOnly cookie

API issues JWT; browser stores it as HttpOnly `auth_token`; web forwards as Bearer.

| Piece | Role |
|-------|------|
| `AuthCookie` | Set/forget cookie (`expires_in`) |
| `AuthenticateWithJwtCookie` | Bind cookie to `api` guard (never rejects — bad token = guest) |
| `RefreshJwtCookie` | Silent refresh near `exp` or when expired-but-refreshable; rewrites cookie + re-binds guard |
| `web.auth` / `web.guest` | Redirect gates (run **after** refresh) |
| `IntendedUrl` | Post-login return URL (full-page GET only; excludes bell/datatable/tree) |
| `IssuesAuthCookie` | Login/register → cookie + redirect |

**Session holds CSRF, flash, intended URL only — not identity.** If the admin is “kicked out”, debug `auth_token` and **Settings → Platform** JWT knobs (`platform.jwt.ttl`, `platform.jwt.refresh_ttl`) first, not `SESSION_LIFETIME`.

### Silent refresh (sliding access)

Access JWT hard-expires at `platform.jwt.ttl` (default 60 minutes; runtime via `setting_config('jwt.ttl')`). Blade never used Laravel’s session guard for login, so without refresh every protected page dies at TTL even while the user is active.

`RefreshJwtCookie` (web stack, after `AuthenticateWithJwtCookie`):

1. Cookie present + valid + remaining life ≤ `platform.jwt.refresh_skew` minutes → `AuthService::refreshFromToken`, queue new `auth_token`, re-bind `api` guard, swap request cookie so same-request `ApiClient` uses the new Bearer.
2. Cookie present + guard guest (expired access) but still inside `platform.jwt.refresh_ttl` → same refresh path so `web.auth` still passes.
3. Past refresh window / blacklisted / invalid → leave guest; `web.auth` redirects to login and forgets the stale cookie.

Multi-tab / parallel AJAX: set `platform.jwt.blacklist_grace_period` (default **30s**) so the predecessor JWT stays acceptable briefly after refresh. Grace period `0` causes random 401s right after silent refresh.

Tunable in **Administration → Settings → Platform** — see [settings.md Tier B](../platform/settings.md#tier-b--platform-group-platform) and [configuration-policy.md](../platform/configuration-policy.md).

### hCaptcha

When `platform.hcaptcha.enabled` is on (sitekey/secret stay env): invisible widget + `MonolithHcaptcha.execute()` on AJAX submit; API validates. Forward `h-captcha-response` on login/register/forgot/2FA.

## API client

| Piece | Role |
|-------|------|
| `ApiClient` | HTTP to same host `/api/v1`; Bearer from cookie; optional `withPrefix()` for non-default mounts; forwards `locale` cookie |
| `ConsumesApi` | Map API envelope → AJAX JSON or redirect; `stayOnPageOnSuccess` for edit-in-place; `apiPayload()` strips `_token`/`_method` (optional field allowlist) |

Web forwards raw input (except `_token`/`_method`); **API validates**. Never call `$request->validate()` in a web controller — that reintroduces dual rule sources and drifts from FormRequests.

| Do | Don't |
|----|--------|
| `$this->apiPayload($request)` or `$this->apiPayload($request, ['field', …])` | Private `payload()` clones per controller |
| Let API FormRequest normalize (prices, booleans) | Reshape domain data in Web before forward |
| Rely on API 422 for bad input | Web-layer `validate()` then proxy |

### AJAX error contract

| Status | UX |
|--------|-----|
| 422 + errors | Inline fields + alert; `ajax-form:invalid` with `detail: { data, form, message }`; `ajax-form:complete` fires in `finally` after every submit |
| **401** (auth death) | Flash `web/session.expired` + redirect login |
| CSRF **419** (web) | `reload: true` after alert — **not** treated as auth logout |
| 403/5xx | Message alert (do **not** clear `auth_token`) |

`ConsumesApi` maps only API **401** to the login redirect. Web CSRF 419 is handled by the exception renderer. Dashboard forgets `auth_token` only on `auth/me` **401**; transport/5xx keeps the cookie and surfaces an error.

Client: `window.MonolithApi.handleResponse()` / `datatableAjaxError()`.

### Deliberate non-HTTP reads

1. Sidebar/navbar → `MenuService::navigationTree` (no self-HTTP per page)  
2. Some form option lists may still Eloquent-read until fully on lookups  

## Routes

Domain files under `routes/web/*.php` loaded from `routes/web.php`. API still authorizes every forwarded call. **Route names stay unprefixed** (`dashboard`, `file-hosting.index`, …); only URI paths carry the shell prefix.

| Surface | Middleware | URI shape | Examples |
|---------|------------|-----------|----------|
| Public / provider | none | site root | `/`, `locale/{locale}`, `/s/f/{uuid}`, `/s/d/{uuid}`, `/payment/ipaymu/*` |
| Guest auth | `web.guest` | site root | `/login`, `/register`, password reset, 2FA challenge |
| Logout | `web.auth` | site root | `POST /logout` (`routes/web/identity-logout.php`) — **not** under `/apps` |
| Authenticated app shell | `web.auth` + `prefix('apps')` | **`/apps/*`** | `/apps/dashboard`, `/apps/users`, `/apps/file-hosting`, `/apps/vtual`, … |

**Why `/apps`:** free product root paths (`/file-hosting`, `/vtual`, …) for future public landings. Authenticated product UI lives at `/apps/{product}`. No legacy redirects from old bare paths (bookmarks to `/dashboard` 404).

Menus store **route names** only (`MenuSeeder`); sidebar resolves via `route()`. Prefer `route()` over hard-coded paths in Blade/JS.

### URL helpers (auth shell)

Authenticated app paths live under `prefix('apps')`. **`url('segment')` / `url('/segment')` join from the site root** and will miss `/apps` — do not use them for auth UI.

| Do | Don't |
|----|--------|
| `route('users.index')`, `route('file-hosting.index')` | `url('users')`, `url('/file-hosting')` |
| `route('domain-access.update', ['productKey' => '__KEY__'])` for JS AJAX templates | `url('domain-access').'/__KEY__'` |
| Inject bases into JS from Blade (`@json(route(...))`, `data-*-url`, `window.monolithQrBaseUrl`) | Hard-code `/apps/…` in JS fallbacks |
| `url('/api/v1/…')` when the intentional target is the versioned API | Guess auth shell paths in client code |
| Named routes for public/guest surfaces (`login`, `logout`, `/s/*`) | Path literals that duplicate the router |

Placeholder tokens (`__KEY__`, `__CODE__`, `__ROUTE__`, `__UPLOAD__`) are fine inside `route()` params — JS replaces them before the request. Guard: `AccessibilityBladeTest` scans adminlte4 blades for `url('…')` on known auth segments under `prefix('apps')`.

**File Hosting SPA:** Blade sets `fhBase` from `route('file-hosting.index')` (and related bases). Client `resolveFhBase()` order: config `fhBase` → derive from `browseUrl` → pathname segment match → `''`. Path joins go through `joinFhPath()` — empty base returns `''` (fail-closed). Do **not** hard-code `/apps/file-hosting` in JS.

**QR checkout:** `window.monolithQrBaseUrl` from `route('qr.show')` in the checkout panel only. Missing inject → empty QR src (no path guess).

### Residual / out of scope (auth URL layout)

| Intentionally not done | Why |
|------------------------|-----|
| Full FH per-action `route()` URL map in JS | SPA size; name-derived base + segment is accepted; segment rename still needs care |
| Product landings on `/file-hosting`, `/vtual`, … | Product work; roots reserved empty |
| Legacy 301/302 bare → `/apps/*` | 301 caches lock product roots; bare product paths reserved for landings |
| Move `POST /logout` under `/apps` | Dual `web.auth` group by design (logout stays at site root) |
| Hard-coded `/apps/…` JS fallbacks | Dual source of truth; regresses path harden |
| Change `/api/v1/*` paths | API contract; web shell prefix only |

Future policy only (not implemented): optional temporary 302 for **non-product** bare paths such as `/dashboard` → `/apps/dashboard` — never permanent 301 onto product roots.

Deploy note: after JS changes under `resources/js/`, run `npm run build` (or Vite dev) so production bundles pick up fail-closed helpers.

<a id="layout-namespace-and-configwebphp"></a>

**Layout namespace:** `WEB_LAYOUT` / `config/web.php` (default `adminlte4`).

## Views

Bundled AdminLTE 4 / Bootstrap 5 / jQuery **3.7.1** / Select2 / DataTables 3 / Vue 3 (npm + Vite). Script order contract: `app.js` (vendor + `window.bootstrap` + `MonolithApi` + `ajax-form` + Select2 auto-init) → **`bell.js`** (when `@can('view notification')`, also in `assets-scripts`) → page `$scripts` → `@stack('scripts')`. Layout renders `assets-scripts` then `$scripts` / `@stack('scripts')` — page JS **must** use `x-slot:scripts` or `@push('scripts')`, never mid-`$slot` `@vite` for JS (CSS may stay early). Do **not** load `bell.js` via `@push('scripts')` (runs after page modules; list pages miss pure-polling health). Skip-link is the first child of `<body>`, **outside** `.app-wrapper` (AdminLTE 4 CSS grid; unnamed children break footer/sidebar placement). Vendor CSS loaded via `resources/css/vendor.css` in `<head>` (prevents FOUC; DataTables CSS is BS5 theme only). Forms: `x-adminlte4::form.ajax`. Select2: [ui-patterns](ui-patterns.md). Dual-path UI: [Vue islands](#vue-islands-vite-sfc) vs [optimize Blade JS](#dual-path--when-to-use-which).

### Dual-path — when to use which

**Default = optimize existing Blade JS** (ajax-form, DataTables, soft refetch, Echo bridges). Promote a surface to a **Vue island** only when the checklist below passes. Do not add Vue “for consistency.”

**Use a Vue island when ALL of (A) and AT LEAST TWO of (B) hold:**

| | Rule |
|--|------|
| **(A) Gate** (all required) | Interactive subtree is page/product-scoped (not global AdminLTE chrome). Team accepts **XOR cutover**: one orchestrator owns that subtree (no dual-boot with legacy JS on the same listing/shell). |
| **(B) Signals** (≥2) | Rich client state that must stay coherent (selection, queues, multi-panel, soft-nav history). Frequent partial UI updates from mutations and/or Echo (live listing, progress, status). Existing vanilla module is a god-file / tightly coupled DOM patching (related surface ≳1k LOC). Re-renders and derived UI are cheaper as declarative Vue than manual DOM sync. |

**Stay on optimized JS when:**

- Index / form / DataTable + ajax-form is enough
- “Live” = bell → DataTable reload or soft-patch after submit
- Checkout-style soft-update already works without multi-view client routing
- Size alone (“JS file is large”) without page-lifecycle complexity — **size ≠ island**

**XOR rule:** A given auth page surface is either the legacy JS orchestrator **or** a Vue island — never both mounting the same listing/shell.

**Promotion path:** Simple surfaces stay on JS forever unless they grow into (A)+(B).

**Backlog (do not promote until the checklist trips):**

| Surface | Current path | Why not an island yet |
|---------|--------------|------------------------|
| Payment checkout / purchase hub | Vanilla soft-update (`checkout.js`) | Echo + poll + DOM patch on one URL already; size ≠ island — see [payment-gateway](../domains/payment-gateway/README.md#checkout-ui-architecture-vanilla-soft-update--not-a-vue-island) |
| Payment channels admin | Vanilla (`channels-admin.js`) | Dense toggles, no soft-nav lifecycle |
| Inference rate-plan grid / usage events | Vanilla | Multi-row editor; promote only if dual DOM ownership appears |
| vTual claims / credentials | Vanilla + small helpers | Forms + confirm modals; revisit when live ingestion needs multi-view client state |
| Users / menus / settings / logs | Blade + ajax-form + DataTables | Classic CRUD; bell → `exposeForReload` soft DT reload is enough |

Optional later shared helper: thin `useEcho` wrapper if multiple islands subscribe — avoid premature abstraction while only dashboard + FH islands exist.

**Realtime (both paths):** Keep/extend existing Echo + poll (notification bell, File Hosting, payment checkout). Elsewhere prefer soft-update after own mutations + DataTable reload bridges — do not add websockets to every CRUD admin page by default.

### Vue islands (Vite SFC)

Progressive Vue 3 islands on Blade — **not** Inertia, **not** CDN Vue, **not** a monorepo-wide SPA. Blade + AdminLTE keep the shell (layout, sidebar/`MenuService`, breadcrumbs, `web.auth`, CSRF). Interactive subtrees mount as SFCs when the [dual-path checklist](#dual-path--when-to-use-which) says so.

```
Blade mountpoint + JSON bootstrap
        → @vite island entry (resources/js/vue/islands/…)
        → apiFetch / web JSON proxy → ConsumesApi → /api/v1/*
```

| Piece | Role |
|-------|------|
| `vue` + `@vitejs/plugin-vue` | npm; Vite compiles SFCs; `manualChunks` → `vendor-vue` |
| `resources/js/vue/mountIsland.js` | `mountIsland('#el', App, props)` — no-op when `#el` missing; `readIslandBootstrap('[data-…]')` |
| `resources/js/vue/composables/useApi.js` | `apiFetch` / `unwrapData` / `csrfToken` — same-origin, CSRF on mutations, routes envelopes through `window.MonolithApi` when present |
| Island entries | One Vite `input` per island under `resources/js/vue/islands/{name}/main.js` — **not** bundled into global `app.js` |

**Mount contract**

1. Blade: `<div id="vue-…">` + `<script type="application/json" data-vue-…>@json($bootstrap)</script>`.
2. Load the island via `x-slot:scripts` / `@push('scripts')` with `@vite('resources/js/vue/islands/…/main.js')` (after `app.js` so `MonolithApi` exists).
3. Bootstrap may carry **config only** (URLs from `route()`, locale labels, feature flags, first-paint snapshot). Domain lists/mutations still come from web JSON proxies or the API — do not dump full domain catalogs as Blade-only props that bypass the API contract.
4. Prefer existing AJAX web routes (`Accept: application/json` / `expectsJson()`) that already forward via `ConsumesApi`. Direct browser → `/api/v1` still needs a Bearer the SPA does not have today (JWT is HttpOnly cookie); same-host web proxy remains the default.
5. **Mount-fail:** invalid/missing bootstrap must paint a visible alert (FH: `data-vue-mount-failed` / `web/file-hosting.vue_mount_failed`) — never a silent empty mount.

**Reference island:** Dashboard “Live session” card (`#vue-dashboard-session`) — `resources/js/vue/islands/dashboard-session/`. Refresh calls `GET` `route('dashboard')` with JSON Accept; `DashboardController@index` returns `{ status, data }` when `expectsJson()`.

**Domain SPA:** File Hosting authenticated browse — `#vue-file-hosting` / `resources/js/vue/islands/file-hosting/` (single auth island; pure helpers stay JS). Public `/s/*` and standalone Microstock usage stay vanilla unless separately promoted. See [file-hosting — UI architecture / SPA shell](../domains/file-hosting/README.md#ui-architecture--spa-shell). Legacy `file-manager.js` is **retired** (XOR cutover). Cold trash/show → browse soft-nav requires `hasBrowseChrome` (non-empty `config.workspacesUrl`); detail in that FH guide.

**Stay vanilla (soft-update, not an island):** Payment purchase hub `/apps/service-purchases/{uuid}` and shared `checkout-panel` — Blade chrome + `checkout.js` / `purchase-quantity.js` / pure `purchase-pricing-preview.js`. Soft status/routing updates via Echo + poll without hard reload. Not an island unless multi-view soft-nav or dual DOM ownership appears. Details: [payment-gateway — Realtime invoice status](../domains/payment-gateway/README.md#realtime-invoice-status-no-hard-reload).

**Anti-patterns**

| Don't | Why |
|-------|-----|
| Inertia (`Inertia::render`) | Couples Vue to Laravel web props/forms; fights API-first |
| Vue via CDN script tags | No SFCs, weak Vitest, fights the Vite pipeline |
| Vue Router for all of `/apps` | Soft-SPA admin shell is out of scope; FH keeps domain-local routing |
| Dual-boot vanilla orchestrator + Vue on the same FH page | Two orchestrators fight; XOR cutover only |
| Vue island per widget (dropzone-island, panel-island, …) | Overfit; widgets are components on the page island |
| Rewriting Echo / router / part-uploader as SFCs | Pure helpers stay JS |
| Vue island for payment purchase hub “because checkout.js is large” | Soft-update Blade already owns the SPA-ish contract; size ≠ page lifecycle |

Tests: Vitest `resources/js/vue/**/*.test.js` (`npm run test:js`); web JSON/mount guards in domain Feature tests (e.g. `DashboardControllerTest`).

### Display data from API Resources

Blade is an API consumer. Money, dates, and byte sizes arrive **already human-readable** from Eloquent Resources (dual fields). See [platform/crud.md — API Resource presentation](../platform/crud.md#api-resource-presentation-dual-fields).

| Do | Don't |
|----|--------|
| Dump `amount_formatted`, `created_at_formatted` / `created_at_human`, `size_human` | `number_format(...)` on money from API |
| Bind DataTables columns to `*_formatted` / `*_human` | Concatenate `currency + ' ' + raw` in JS |
| Use quota `used_bytes_human` / `quota_bytes_human` | Copy-paste `$formatBytes` closures in Blade |
| Pass preformatted strings into payment hero / checkout callouts | `Carbon::parse(...)->diffForHumans()` in views |

Raw numeric/ISO fields remain available for conditionals (`if ((float) credit_amount > 0)`) and math — never for primary display.

### Theming

Soft override CSS layer (light/dark) under layout assets — keep tokenized variables. **Default is dark** when no user preference is stored in localStorage:

- `resources/js/infra/theme-scroll.js` sets `data-bs-theme` on `<html>` before first paint. Priority: stored `lte-theme` localStorage value → dark fallback. Users who switch to `auto` get `prefers-color-scheme` resolved live; users who explicitly pick `light` get light (stored).
- File-hosting standalone public pages (`public-folder.blade.php`, `public-file.blade.php`) mirror the same logic via inline IIFE: `<html data-bs-theme="dark">` as the HTML attribute fallback, then overridden to light if the user stored `light` or has `prefers-color-scheme: light` and no stored value.
- Navbar toggle has three modes: Light, Dark, Auto (default selected state). Clicking stores the choice and re-applies immediately. System color-scheme change listener re-evaluates only when stored value is absent or `auto`.

- Override file: `resources/css/monolith-overrides.css` (loaded via `@vite` in `assets-head.blade.php`).
- Vendor CSS (AdminLTE, Bootstrap, DataTables BS5, Select2, OverlayScrollbars) bundled in `resources/css/vendor.css` — loaded in `<head>` before overrides to prevent FOUC. Do **not** also import `datatables.net-dt` default CSS (fights the BS5 theme).
- Vendor JS (jQuery 3.7.1, DataTables 3, Select2, Bootstrap, AdminLTE, OverlayScrollbars) bundled in `resources/js/app.js` with `manualChunks` splitting each vendor into a separate chunk. Bootstrap ESM is bound to `window.bootstrap` for CDN-era consumers. **Vite ESM page entries must use `window.bootstrap` (or `import { Modal } from 'bootstrap'`)** — bare `bootstrap.Modal` is a ReferenceError in modules (fixed for `vtual/confirm-modal.js`). Classic inline scripts may still resolve the global via `window`.
- Card hover lift is **scoped** to interactive cards (`.card.is-clickable`, `.fh-file-card`, `a.card`). The global `.card:hover` lift was removed to avoid jank on static cards.
- Dark-mode gradient-end token is `--monolith-gradient-end: #312e81` (indigo). The previous `#1a2e1a` was a green-hue typo in an indigo/slate palette.
- Invalid feedback in `form.input` is rendered as a **sibling** after `.input-group`, not inside it (Bootstrap requirement).
- `channels-admin.js` toggles channel visibility via `data-hidden="true|false"` (matches the CSS attribute selector); inline `style.display` was removed. Route list reordering preserves focus across DOM moves.
- Select2 dark-mode overrides are **unlayered** in `resources/css/monolith-overrides.css` (no `!important`). Match select2-bootstrap-5-theme specificity for closed/selected text (`[data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered`) — otherwise theme `color: #212529` wins on dark surfaces. Never re-wrap these rules in `@layer`.
- CSS tokens consolidated: `--pc-*` / `--ois-*` kind/status tokens merged into `--monolith-*`; hex fallbacks stripped (vendor CSS + `@vite` guarantee variable availability).
- Guarded by `tests/Feature/Http/Controllers/Web/ThemeOverrideCssTest.php`.

### UX patterns

- **Cancel/back buttons**: profile forms (`profile/password`, `profile/two-factor`) ship a Cancel button linking back to `dashboard`.
- **Filter reset**: `error-logs/index` exposes a reset icon next to Apply that drops the query string.
- **Restore confirm**: `menus/trashed` no longer prompts `confirm()` on restore — restore is non-destructive, so the prompt was backwards severity.
- **Submit spinners**: every AJAX submit button (navbar logout, mark-all-read, `menus/index` delete forms) carries a `[data-form-submit-spinner]` span toggled by the ajax-form handler.
- **Checkout toasts**: `checkout.js` uses `showCheckoutToast(message, type)` (`.monolith-toast` container, `role="status"` + `aria-live`). `window.alert` is forbidden. Invoice/purchase show also soft-updates payment status (hero/steps/panel/sidebar) without hard reload — see [payment-gateway — Realtime invoice status](../domains/payment-gateway/README.md#realtime-invoice-status-no-hard-reload).
- **Unified toast**: all toast routing goes through `window.MonolithSwal.toastMessage(message, type)` (SweetAlert2 toast mixin). Legacy `.monolith-toast` CSS component retained for domain consumers (checkout, file-hosting Vue island, channels-admin, domain-access, inference) that build toast elements manually. No per-domain toast CSS duplication.
- **Confirm paid**: the manual confirm button ships a `title` + sibling help text (`web/payments.checkout.confirm_paid_help`) explaining why manual sync exists.
- **Service checkout dedup**: `services/show` renders a single checkout form. `Gate::any(['purchase service', 'initiate payment'])` (block `@php … @endphp`) gates it — same pair `ServicePolicy::purchase` checks. Do **not** inline `@php(...)` here (see Blade `@php` inline gotcha below).
- **Payment-channel priority autosave**: priority inputs fire AJAX on `change` and the label carries an italic "saved automatically" hint so users are not surprised by the absence of a Save button.
- **Local QR generator**: `instructionMedia.js` routes `qrString` fallbacks to `/qr` (`QrCodeController`, `chillerlan/php-qrcode`) — the payment payload never leaves the app.
- **Clickable breadcrumb parents**: every parent trail in `routes/breadcrumbs.php` passes a `route()` URL — `activity-logs.index`, `notifications.index`, `profile.password.edit` included. The renderer only links non-last items, so a parent without a URL renders as dead text. Policy: all parents must be clickable.
- **Menu delete cascade modal**: `menus/index` ships one delete trigger per row that opens `#menuDeleteConfirmModal`. The modal's cascade checkbox swaps the form `action` to the cascade URL via JS (`data-menu-cascade-url`, `data-menu-confirm-template`). No more inline twin delete buttons.
- **Paired card alignment**: `users/edit` keeps the edit form and recent-activity cards flush (no `mt-3`). When activity is empty, the partial renders an `empty-state` inside the card so the right column does not collapse.
- **File-hosting toolbar symmetry**: `.fh-toolbar` carries a full border + uniform radius (not a half-open bar). Empty listing colspan is owned by Vue `App.vue` (not Blade `$tableColspan`).
- **File-hosting Vue shell**: `file-hosting/index.blade.php` mounts `#vue-file-hosting` + JSON bootstrap and includes live modal/offcanvas shells (`workspace-modals`, `microstock-review-panel`, `usage-offcanvas`, `file-folder-modals`, `team-offcanvas`). Listing, toolbar, upload, and row actions live in `App.vue` — archived `toolbar-zone` / `browse-views` / `trash-show-panels` under `resources/views/_archive/adminlte4/file-hosting/partials/` are not the live paint path.
- **DataTables factory**: `initDataTable({ tableId, ajaxUrl, columns, order, language, exposeForReload, onReady })` in `datatable-init.js` (Vite entry). Six index views load it via `@vite` and call `window.initDataTable(...)` from inline scripts — no ESM import (Vite 8/Rolldown tree-shakes named exports from entry modules). `exposeForReload` stores instances in `window.dataTables[tableId]` for realtime bell → DT reload bridge. jQuery + DataTables binding (`window.jQuery`, `DataTable.use(jQuery)`) lives in `infra/jquery-init.js` and runs before any consumer — DataTables 3 does not auto-detect `window.jQuery` for `.fn.DataTable` attachment.
- **JS infrastructure modules**: `api-response-handler.js`, `ajax-form.js`, `theme-scroll.js` (theme toggle + sidebar scrollbar), and `select2-init.js` are bundled in `app.js` (Vite). No inline `<script>` blocks for these; the old Blade partials were removed. Config passed via `<script type="application/json" data-monolith-api-messages>`.
- **SweetAlert2**: `sweetalert2` (npm) bundled in `app.js` via `infra/sweetalert.js`. Exposes `window.MonolithSwal` (`alert`, `confirm`, `toastMessage`, `Swal`). Replaces all `window.alert` / `window.confirm`. Form confirm delegation: `[data-swal-confirm]` attribute on `<form>` triggers `MonolithSwal.confirm(message, '', { dangerMode: true })` before submit (global listener in `app.js`). i18n via `<script type="application/json" data-monolith-swal-labels>` JSON blob (keys: `ok`, `confirm`, `cancel` from `lang/{en,id,ja}/ui.php` under `swal.*`). Details: [ui-patterns — SweetAlert2 / confirmations](ui-patterns.md#sweetalert2--confirmations).
- **Sidebar search**: `sidebar-search.js` loads via `@vite` + `@push('scripts')` (after `app.js`). Config from `<script type="application/json" data-sidebar-nav-config>`. No inline JS.
- **Header actions**: index card headers expose their primary action via `<x-slot:actions>` (e.g. "Add" / "Create" / "Export" buttons), not a bespoke `d-flex` div in the card body. Every sibling index view follows this slot — `invoices/index`, `users/index`, `subscriptions/index`, `payment-gateways/index`, `inference/providers/index`, `inference/model-cards/index`. Create/add buttons prefix the label with `<i class="bi bi-plus-lg me-1"></i>` for consistency.
- **Icon-only buttons**: every icon-only button (row actions, view toggles, toolbars) carries both `aria-label` and `title` (WCAG 2.1 AA — see [ui-patterns — Accessibility](ui-patterns.md#accessibility-aria)). Icons use `aria-hidden="true"` so screen readers skip the glyph. The shared `renderDatatableActions` renderer sets both from `action.label`; manual Blade rows must do the same.
- **SweetAlert2 confirm attribute**: forms marked `data-swal-confirm` must use the Blade echo form `data-swal-confirm="{{ __('...') }}"` (not `@json(...)` / `@js(...)`). `@json()` / `@js()` run `json_encode` with `JSON_HEX_QUOT`, which escapes the message's double quotes into `\u0022` tokens that render literally inside the SweetAlert2 dialog. Reference: `file-hosting/blocklist.blade.php`, `file-hosting/partials/team-offcanvas.blade.php` (correct), `services/index.blade.php` & `product-activations/show.blade.php` (fixed).
- **Auto-submit delegation**: forms that should submit on a child `change` event carry `data-auto-submit` on the `<form>` and `data-auto-submit-trigger` on the triggering control (e.g. a `<select>` filter). A global listener in `app.js` handles the submit — no inline `onchange="this.form.submit()"`. Replaces the inline-JS pattern previously on `product-activations/show`'s state filter.
- **Direct-upload browse trigger**: auth FH dropzone is Vue (`FhDropzone.vue`) — browse button / OS drop / click-to-open open the file picker; queue UI is reactive. Legacy `direct-upload.js` `initDirectUpload` remains for non-Vue auto-init only.

### Blade `@php` inline gotcha

The inline `@php($expr)` directive compiles via a regex that matches the **first** closing paren. When `$expr` itself contains parens — any method call like `Gate::allows('initiate payment')`, a closure, or a nested function — the matcher consumes the wrong `)` and emits malformed PHP. Everything after the broken directive (`@else`, `@endcan`, `@if`, `@php`, `@endif`) then leaks through as raw text instead of compiled directives, leaving variables undefined and pages 500'ing (`Undefined variable $isAddonPlan` on `services/show`).

| Do | Don't |
|----|-------|
| Block form `@php` … `@endphp` for any expression with its own parens | Inline `@php(Gate::allows('initiate payment'))` |
| Inline `@php($flag = true)` only for trivial scalar assignments | Inline `@php($x = SomeClass::method($arg))` |

Hit on `services/show` (fixed): the `@can/@else/@endcan` + inline `@php(...)` block was replaced with a single block `@php … @endphp` using `Gate::any([...])`. See [service — Web (Blade)](../domains/service/README.md#web-blade).

## Partial and component layout

Views live under `resources/views/{layout}/` where `{layout}` is `config('web.layout')` (default `adminlte4`). [`WebLayout`](../../app/Support/Web/WebLayout.php) resolves screen and partial names — never hard-code `adminlte4.` in domain Blade.

| Kind | Path | Include / tag |
|------|------|----------------|
| Full page | `{layout}/{domain}/{action}.blade.php` | `webView('users.edit')` in controller |
| Domain fragment | `{layout}/{domain}/partials/{name}.blade.php` | `@include(config('web.layout').'.users.partials.form', …)` or `@include(\App\Support\Web\WebLayout::partial('users.partials.form'), …)` |
| Reusable UI (tag) | `{layout}/components/{group}/{name}.blade.php` | `<x-adminlte4::form.input …>` |
| Layout shell (composer-bound) | `{layout}/components/partials/` | `<x-adminlte4::layouts.app>` includes sidebar/navbar — **do not move** without updating [`NavigationServiceProvider`](../../app/Providers/Domain/NavigationServiceProvider.php) / [`NotificationServiceProvider`](../../app/Providers/Domain/NotificationServiceProvider.php) |
| Shared layout misc | `{layout}/partials/` | e.g. `partials/breadcrumbs` via `WebLayout::breadcrumbsView()` |

**Rules:**

- Partials belong in a `partials/` subdirectory, not beside index/create/edit pages. Drop the leading `_` prefix when the file is already under `partials/`.
- `@include` inherits parent scope; `<x-adminlte4::…>` components only receive explicit attributes/slots — do not convert includes with implicit parent variables without passing data.
- Do not move `@vite` into shared partials (bundle bleed / duplicate loads). Page shells own asset entries.
- Extract a shared partial only when **≥2 active callers** share the same data/DOM contract (e.g. `vtual/partials/channel-add-form`, `payments/income/partials/breakdown-table`).
- Orphan Blade (no caller, superseded by Vue islands) → `resources/views/_archive/` — not refactored with active surface. Inventory: [partial-manifest.md](partial-manifest.md).

**Anti-patterns (do not consolidate):** one generic CRUD `_form`; cross-domain status badges; merging vTual orphan channel tables with profile manage channels (different RBAC, routes, DOM ids, JS).

## Concurrent server note

Web → API is a **real HTTP** call to the same app. PHP built-in server single-threaded can deadlock self-calls — use Laragon/Apache or multiple workers.

## Related

- [domains/*](../domains/) for domain-specific web proxies  
