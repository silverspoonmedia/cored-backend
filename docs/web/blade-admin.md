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

Domain files under `routes/web/*.php` loaded from `routes/web.php` with `web.guest` / `web.auth`. API still authorizes every forwarded call.

Major groups: auth guest, dashboard/profile, users (+ datatable proxy), menus, notifications, activity-logs, error-logs, payments/invoices, services, file-hosting.

Layout namespace: `WEB_LAYOUT` / `config/web.php` (default `adminlte4`).

## Views

CDN AdminLTE 4 / Bootstrap 5 / Icons. Scripts order: `MonolithApi` → `ajax-form` → page scripts. Forms: `x-adminlte4::form.ajax`. Select2: [ui-patterns](ui-patterns.md).

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
- **Checkout toasts**: `checkout.js` uses `showCheckoutToast(message, type)` (`.ois-toast` container, `role="status"` + `aria-live`). `window.alert` is forbidden.
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
