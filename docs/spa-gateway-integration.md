# Three-project integration (Monolith + Gateway + Vue)

**AI agents:** read the canonical [AI Agent Guide](ai-agent-guide.md). Daily work stays in Monolith; when HTTP contracts change, add a [downstream changelog](downstream-changelog/README.md) entry instead of editing Gateway or Frontend in the same task. Use this guide for architecture context and downstream sync sessions.

End-to-end public stack:

| Project | Role | URL (Laragon local) |
|---------|------|---------------------|
| **monolith** | Private API + optional Blade admin | `http://monolith.test` |
| **monolith-gateway** | Public BFF, JWT cookie, CORS | `http://monolith-gateway.test` |
| **monolith-frontend** | Vue SPA | `http://localhost:5173` (`npm run dev`) or `http://localhost:8788` (`npm run preview:local`) |

## Monolith internal layout vs Gateway/Frontend repos

Monolith PHP code is organized as a **logical monorepo** under `app/Domain/*` (see [domain-modules.md](./domain-modules.md)). **Gateway and Frontend remain separate git repositories** — they do not import Monolith PHP classes.

| Change type | Gateway | Frontend |
|-------------|---------|----------|
| Namespace / folder moves only | No change | No change |
| Same public URL, route file split | No change | No change |
| API URL or JSON response change | Update proxy/routes | Update `src/api/*` |
| New menu/DataTable route names | `config/vue-routes.php` | `src/router/index.ts` |

Full adaptation matrix: [internal-monorepo-assessment.md](./internal-monorepo-assessment.md#adaptation-contract-gateway--frontend).

Cross-project test script (from Monolith root): `.\scripts\test-workspace.ps1`

## Local wiring (Laragon)

1. **Monolith** — `GATEWAY_ENFORCE=false` locally; `GATEWAY_SECRET=local-gateway-secret`.
2. **Gateway** — `MONOLITH_BASE_URL=http://monolith.test`, `MONOLITH_GATEWAY_SECRET=local-gateway-secret`, `APP_URL=http://monolith-gateway.test`, `FRONTEND_URL=http://localhost:8788`.
3. **Frontend** — leave `VITE_API_BASE_URL` **empty** so axios uses relative `/api/*` URLs. Vite proxies those requests to `http://monolith-gateway.test` (see `vite.config.ts`).

Do **not** point `VITE_API_BASE_URL` at `http://localhost:8000` unless you actually run the gateway with `php artisan serve --port=8000`.

Start services:

```powershell
# Monolith + Gateway — Laragon vhosts (monolith.test, monolith-gateway.test)

# Frontend with API proxy (recommended for HttpOnly cookie auth on HTTP)
cd monolith-frontend
npm run dev
# or production-like preview on port 8788:
npm run preview:local
```

`npm run preview` (Wrangler Pages dev) does **not** use the Vite proxy — set `VITE_API_BASE_URL=http://monolith-gateway.test` in `.env` before build if you need that mode, and expect cross-origin cookie limitations on plain HTTP.

Sign in with seeded credentials (`admin@example.com` / `123456789`). The gateway sets `auth_token`; the SPA never reads the JWT.

### Local dev ports and CORS

| Mode | SPA URL | `FRONTEND_URL` on gateway | Why |
|------|---------|---------------------------|-----|
| `npm run dev` | `http://localhost:5173` | `http://localhost:8788` (default in gateway `.env.example`) | Vite proxies `/api` and `/broadcasting` to `monolith-gateway.test` — browser sees same-origin `:5173`, so CORS is not involved |
| `npm run preview:local` | `http://localhost:8788` | `http://localhost:8788` | Vite preview with same proxy; matches `FRONTEND_URL` for cross-origin tests |

`vite.config.ts` sets `cookieDomainRewrite: 'localhost'` on the proxy so HttpOnly cookies set by the gateway are accepted on the dev origin.

Do **not** set `VITE_API_BASE_URL` to the gateway URL while developing on `:5173` unless you also add `http://localhost:5173` to gateway CORS — the default setup avoids that by proxying.

## Two API client paths

| Client | Browser → | Monolith path | Auth |
|--------|-----------|---------------|------|
| **Vue SPA** | Cloudflare Pages → **Gateway** `/api/*` | Gateway forwards to `MONOLITH_BASE_URL/api/v1/*` (or `/api/playground/*`) | HttpOnly `auth_token` cookie on gateway; gateway sends `Authorization: Bearer` upstream |
| **Blade admin** | Monolith web → **same-host** `/api/v1/*` | `ApiClient` (`app/Support/Api/ApiClient.php`) | JWT from `auth_token` cookie + `X-Gateway-Secret` when `GATEWAY_SECRET` is set |

`GATEWAY_ENFORCE=false` locally allows Blade and PHPUnit to call Monolith API without going through the gateway vhost. In production, `GATEWAY_ENFORCE=true` blocks direct public access to Monolith; the SPA still uses Gateway exclusively.

## Playground API (`/api/playground/*`)

Monolith playground routes live on the **global** prefix `/api/playground/*` (not `/api/v1/*`). They are **disabled when `APP_ENV=production`** — see [playground.md](./playground.md).

Gateway exposes a transparent catch-all:

```
ANY /api/playground/{path}  →  Monolith /api/playground/{path}
```

The Vue SPA orders screens call `/api/playground/orders` (and attachment uploads under `/api/playground/attachments/uploads`). In production, if playground is off on Monolith, those SPA screens will fail — playground is a **non-production demo surface**.

## Verify connectivity

```powershell
curl http://monolith-gateway.test/api/ping
```

Login requests from the browser should hit `http://localhost:5173/api/v1/auth/login` (or `:8788` with `preview:local`) — **not** `localhost:8000`.

## Gateway database vs Monolith database

The gateway is a **stateless HTTP proxy** for `/api/*`. It does **not** run login queries against its own `users` table.

| Layer | Handles auth DB? | Notes |
|-------|------------------|-------|
| **monolith-gateway** | No | `AuthProxyController` forwards to Monolith via `MonolithClient`. Do **not** set `DB_CONNECTION` (or any `DB_*`) in gateway `.env` on Laragon/mod_php — Apache reuses PHP workers across vhosts and `putenv()` from the gateway can leak `sqlite` into Monolith requests in the same worker. Use `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. |
| **monolith** | Yes | All `users` queries, JWT, RBAC live here (`/api/v1/*`). |

If the Vue app shows a SQL error during login, read the stack trace:

- Paths under `monolith\app\...` → **Monolith upstream** failed (gateway only relayed the JSON).
- `Connection: sqlite, Database: monolith` on Monolith usually means either:
  - A **stale SQLite file** `./monolith` in the Monolith project root (created when `DB_CONNECTION` fell back to `sqlite` while `DB_DATABASE=monolith` was set for MySQL). Delete that file, keep `DB_CONNECTION=mysql`, run `php artisan config:clear`.
  - **Gateway `DB_CONNECTION=sqlite` in `.env`** on Laragon (shared Apache worker pollutes Monolith). Remove all `DB_*` from gateway `.env` and restart Apache.

Do not confuse gateway `database/database.sqlite` with Monolith's MySQL database `monolith`.

## SPA vs Blade parity

The Vue SPA mirrors the Blade admin surface at `resources/views/adminlte4/*`. Route names align with [`monolith-gateway/config/vue-routes.php`](../../monolith-gateway/config/vue-routes.php).

| Area | Blade | SPA | Status |
|------|-------|-----|--------|
| Auth (login/register/2FA/reset) | yes | yes | Parity — AdminLTE-matched card; inline locale switcher in card header |
| Dashboard + quick links | `@can` | `hasPermission` + route guards | Parity |
| Users CRUD + activity history | yes | yes | Parity |
| Orders (playground) show/bulk/form | yes | yes | Parity |
| Menus CRUD + trashed restore | yes | yes | Parity |
| Activity logs index/show | yes | yes | Parity |
| Notifications index + bell | polling/Echo | polling + Echo (`VITE_BROADCAST_ENABLED`) | Parity |
| Approvals batch/reject/reassign | yes | yes | Parity |
| Approval workflow admin | yes | yes | Parity |
| AdminLTE shell (theme, breadcrumbs, sidebar search) | yes | yes | Parity |
| Locale persist (`users.locale`) | `GET /locale/{locale}` | `POST /api/v1/auth/locale` + guest `locale` cookie in JS | Parity — see [Locale (SPA)](#locale-spa) below |

### Realtime notifications (SPA)

When Monolith has `APP_NOTIFICATION_TYPE=realtime` and a configured `BROADCAST_CONNECTION` (Reverb/Pusher), set on the **frontend build** env (Cloudflare Pages or `monolith-frontend/.env`):

```env
VITE_BROADCAST_ENABLED=true
VITE_PUSHER_APP_KEY=...
VITE_PUSHER_APP_CLUSTER=mt1
VITE_PUSHER_HOST=127.0.0.1
VITE_PUSHER_PORT=6001
VITE_PUSHER_SCHEME=http
```

| Traffic | Path |
|---------|------|
| Websocket connection | **Direct** to `VITE_PUSHER_HOST`:`VITE_PUSHER_PORT` (not through Gateway) |
| Channel auth | `POST /broadcasting/auth` — Vite dev/preview proxies to gateway → Monolith |

Vite dev/preview proxies `/broadcasting` to the gateway, which forwards to Monolith `/broadcasting/auth` with the `auth_token` cookie.

If websocket auth fails, the bell falls back to HTTP polling (`VITE_NOTIFICATION_POLLING_INTERVAL`, default 60s) — same behaviour as Blade.

### Locale (SPA)

Blade uses `GET /locale/{locale}` (cookie + optional `users.locale` update). The SPA uses a different surface:

1. **Guest:** `localeStore` sets `document.cookie = locale=…` and `localStorage.locale`.
2. **Authenticated:** `POST /api/v1/auth/locale` persists `users.locale` on Monolith.
3. **Every API call:** Axios sends `Accept-Language` from `localStorage` (informational); Monolith `SetLocale` resolves **`users.locale` → `locale` cookie → `config('app.locale')`** — it does **not** use `Accept-Language` alone.

Gateway `MonolithClient` forwards the incoming `locale` cookie (and `Accept-Language`) upstream so SPA and Monolith stay aligned.

Full Blade/API rules: [localization.md](./localization.md). SPA store details: `monolith-frontend/docs/stores-and-realtime.md`.

### hCaptcha (SPA auth forms)

When Monolith has `HCAPTCHA_ENABLED=true`, the SPA build must set matching frontend vars or login/register will fail server-side validation:

```env
VITE_HCAPTCHA_ENABLED=true
VITE_HCAPTCHA_SITEKEY=...
```

Monolith `HCAPTCHA_SECRET` stays on the server only. See [jwt-authentication.md](./jwt-authentication.md).

## SPA frontend implementation

Recent UX work (skeleton loading, responsive shell, auth layout parity) lives entirely in **monolith-frontend**. No gateway or Monolith API changes were required.

| Guide (monolith-frontend) | Topic |
|---------------------------|-------|
| `docs/audit-summary.md` | What changed in each of the three projects |
| `docs/architecture.md` | Stack, axios cookie auth, Vite proxy, folder layout |
| `docs/async-fetch-ux.md` | `useAsyncData`, `AppFetchState`, skeleton/error components |
| `docs/responsive-ui.md` | `useBreakpoint`, mobile layouts, DataTables Responsive |
| `docs/auth-layout.md` | AdminLTE auth card, locale switcher, mobile spacing |
| `docs/testing.md` | Vitest vs Playwright, 84 unit tests |

Path on disk: `monolith-frontend/docs/` (separate project directory from Monolith and Gateway).

## Gateway implementation

Gateway-specific internals are documented in **monolith-gateway** `docs/`:

| Guide | Topic |
|-------|-------|
| `docs/architecture.md` | Routes, proxy flow, file map |
| `docs/auth-cookie.md` | `auth_token` HttpOnly cookie, token stripping |
| `docs/monolith-client.md` | Upstream HTTP, headers, multipart |
| `docs/vue-route-transforms.md` | `vue-routes.php`, navigation/DataTable/notification URL rewriting |
| `docs/aggregate-endpoints.md` | BFF bootstrap + form aggregate endpoints |
| `docs/configuration.md` | Env vars, CORS, stateless Laragon rules |
| `docs/testing.md` | PHPUnit |

SPA UX work may require gateway aggregate endpoints or new route mappings in `config/vue-routes.php` when screens fan out to many Monolith resources on load.

### API aggregation (latency)

The SPA is on Cloudflare POPs; the gateway is in Singapore. To avoid multiple cross-region round trips per page load, the gateway exposes:

| Endpoint | Replaces (browser calls) |
|----------|--------------------------|
| `GET /api/v1/bootstrap` | `auth/me` + 2× `navigation/menus` + `notifications/unread-count` |
| `GET /api/v1/users/{id}/edit-context` | `users/assignable-roles` + `users/{id}` + `users/{id}/activity-logs` |
| `GET /api/v1/navigation/menus/form-options` | `navigation/menus/{id}` only (edit); create returns `{}` |
| `GET /api/v1/lookups/{users,menus,roles}` | Paginated Select2 options (proxied; see [form-selects.md](./form-selects.md)) |

Monolith API is unchanged. See gateway `docs/aggregate-endpoints.md`.

### Programmatic API access only

Gateway rejects **direct browser `GET` navigation** to `/api/*` (address bar, new tab) while allowing SPA programmatic fetch and all `POST`/`PUT`/`DELETE` mutations. `OPTIONS` preflight is always allowed. The Frontend sends `X-Requested-With: XMLHttpRequest` via Axios.

**Toggle:** `GATEWAY_ENFORCE_PROGRAMMATIC_API` on the gateway (default `true`). Aggregate `GET` endpoints (`bootstrap`, `edit-context`, `form-options`) are subject to the same middleware.

Details: gateway [programmatic-api-access.md](../../monolith-gateway/docs/programmatic-api-access.md).

### Static Frontend / Gateway-first

The Vue SPA deploys as **static `dist/` on Cloudflare Pages** — no Pages Functions or Workers for API/BFF logic. Any capability that might otherwise be added at the edge must be assessed for **Gateway implementation first**; Frontend edge compute requires explicit approval after discussion. See Frontend [static-only-policy.md](../../monolith-frontend/docs/static-only-policy.md) and canonical [ai-agent-guide.md](./ai-agent-guide.md).

## Production

- `GATEWAY_ENFORCE=true` on Monolith
- Shared strong `GATEWAY_SECRET` / `MONOLITH_GATEWAY_SECRET`
- `AUTH_COOKIE_SECURE=true` and `AUTH_COOKIE_SAME_SITE=none` on gateway
- `FRONTEND_URL` = deployed SPA public HTTPS origin (Cloudflare Pages)
- `VITE_API_BASE_URL` = deployed gateway public HTTPS origin (VPS) — **different** from `FRONTEND_URL`
- `GATEWAY_ENFORCE_PROGRAMMATIC_API=true` on gateway (default; leave enabled in production)
