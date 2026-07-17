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
| `web.auth` / `web.guest` | Redirect gates |
| `IntendedUrl` | Post-login return URL (full-page GET only; excludes bell/datatable/tree) |
| `IssuesAuthCookie` | Login/register → cookie + redirect |

Session holds CSRF, flash, intended URL only — **not** identity.

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
| 401/419 auth | Flash + redirect login |
| CSRF 419 | `reload: true` after alert |
| 403/5xx | Message alert |

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

Soft override CSS layer (light/dark) under layout assets — keep tokenized variables; parity with SPA when theming changes (changelog if HTTP/CSS contract affects SPA).

## Concurrent server note

Web → API is a **real HTTP** call to the same app. PHP built-in server single-threaded can deadlock self-calls — use Laragon/Apache or multiple workers.

## Related

- [domains/*](../domains/) for domain-specific web proxies  
- [integration/spa-gateway.md](../integration/spa-gateway.md) — Vue path  
