# Web frontend (Blade + AdminLTE 4)

API-first Blade admin: every web controller forwards to `/api/v1/*`. **No business logic, Form Requests, or `$this->authorize()` in the web layer.**

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

**Session holds CSRF, flash, intended URL only — not identity.** If the admin is “kicked out”, debug `auth_token` / `JWT_TTL` / refresh window first, not `SESSION_LIFETIME`.

### Silent refresh (sliding access)

Access JWT hard-expires at `JWT_TTL` (default 60 minutes). Blade never used Laravel’s session guard for login, so without refresh every protected page dies at TTL even while the user is active.

`RefreshJwtCookie` (web stack, after `AuthenticateWithJwtCookie`):

1. Cookie present + valid + remaining life ≤ `JWT_REFRESH_SKEW` minutes → `AuthService::refreshFromToken`, queue new `auth_token`, re-bind `api` guard, swap request cookie so same-request `ApiClient` uses the new Bearer.
2. Cookie present + guard guest (expired access) but still inside `JWT_REFRESH_TTL` → same refresh path so `web.auth` still passes.
3. Past refresh window / blacklisted / invalid → leave guest; `web.auth` redirects to login and forgets the stale cookie.

Multi-tab / parallel AJAX: set `JWT_BLACKLIST_GRACE_PERIOD` (default **30s**) so the predecessor JWT stays acceptable briefly after refresh. Grace period `0` causes random 401s right after silent refresh.

Env knobs: [identity](../domains/identity/README.md#configuration), [environment](../platform/environment.md).

### hCaptcha

When `HCAPTCHA_ENABLED=true`: invisible widget + `MonolithHcaptcha.execute()` on AJAX submit; API validates. Forward `h-captcha-response` on login/register/forgot/2FA.

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
| 422 + errors | Inline fields + alert; `ajax-form:invalid` |
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

Layout namespace: `WEB_LAYOUT` / `config/web.php` (default `adminlte4`).

## Views

CDN AdminLTE 4 / Bootstrap 5 / Icons. Scripts order: `MonolithApi` → `ajax-form` → page scripts. Forms: `x-adminlte4::form.ajax`. Select2: [ui-patterns](ui-patterns.md).

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

Soft override CSS layer (light/dark) under layout assets — keep tokenized variables.

- Override file: `resources/css/monolith-overrides.css` (loaded via `@vite` in `assets-head.blade.php`).
- Card hover lift is **scoped** to interactive cards (`.card.is-clickable`, `.fh-file-card`, `a.card`). The global `.card:hover` lift was removed to avoid jank on static cards.
- Dark-mode gradient-end token is `--monolith-gradient-end: #312e81` (indigo). The previous `#1a2e1a` was a green-hue typo in an indigo/slate palette.
- Invalid feedback in `form.input` is rendered as a **sibling** after `.input-group`, not inside it (Bootstrap requirement).
- `channels-admin.js` toggles channel visibility via `data-hidden="true|false"` (matches the CSS attribute selector); inline `style.display` was removed. Route list reordering preserves focus across DOM moves.
- Guarded by `tests/Feature/Http/Controllers/Web/ThemeOverrideCssTest.php`.

### UX patterns

- **Cancel/back buttons**: profile forms (`profile/password`, `profile/two-factor`) ship a Cancel button linking back to `dashboard`.
- **Filter reset**: `error-logs/index` exposes a reset icon next to Apply that drops the query string.
- **Restore confirm**: `menus/trashed` no longer prompts `confirm()` on restore — restore is non-destructive, so the prompt was backwards severity.
- **Submit spinners**: every AJAX submit button (navbar logout, mark-all-read, `menus/index` delete forms) carries a `[data-form-submit-spinner]` span toggled by the ajax-form handler.
- **Checkout toasts**: `checkout.js` uses `showCheckoutToast(message, type)` (`.ois-toast` container, `role="status"` + `aria-live`). `window.alert` is forbidden. Invoice/purchase show also soft-updates payment status (hero/steps/panel/sidebar) without hard reload — see [payment-gateway — Realtime invoice status](../domains/payment-gateway/README.md#realtime-invoice-status-no-hard-reload).
- **Confirm paid**: the manual confirm button ships a `title` + sibling help text (`web/payments.checkout.confirm_paid_help`) explaining why manual sync exists.
- **Service checkout dedup**: `services/show` renders a single checkout form. `@can('purchase service')` short-circuits; otherwise `Gate::allows('initiate payment')` gates the same form.
- **Payment-channel priority autosave**: priority inputs fire AJAX on `change` and the label carries an italic "saved automatically" hint so users are not surprised by the absence of a Save button.
- **Local QR generator**: `instructionMedia.js` routes `qrString` fallbacks to `/qr` (`QrCodeController`, `chillerlan/php-qrcode`) — the payment payload never leaves the app.
- **Clickable breadcrumb parents**: every parent trail in `routes/breadcrumbs.php` passes a `route()` URL — `activity-logs.index`, `notifications.index`, `profile.password.edit` included. The renderer only links non-last items, so a parent without a URL renders as dead text. Policy: all parents must be clickable.
- **Menu delete cascade modal**: `menus/index` ships one delete trigger per row that opens `#menuDeleteConfirmModal`. The modal's cascade checkbox swaps the form `action` to the cascade URL via JS (`data-menu-cascade-url`, `data-menu-confirm-template`). No more inline twin delete buttons.
- **Paired card alignment**: `users/edit` keeps the edit form and recent-activity cards flush (no `mt-3`). When activity is empty, the partial renders an `empty-state` inside the card so the right column does not collapse.
- **File-hosting toolbar symmetry**: `.fh-toolbar` carries a full border + uniform radius (not a half-open bar). The list-view empty row spans all 8 columns.

## Concurrent server note

Web → API is a **real HTTP** call to the same app. PHP built-in server single-threaded can deadlock self-calls — use Laragon/Apache or multiple workers.

## Related

- [domains/*](../domains/) for domain-specific web proxies  
