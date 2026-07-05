# Three-project integration (Monolith + Gateway + Vue)

End-to-end public stack:

| Project | Role | URL (Laragon local) |
|---------|------|---------------------|
| **monolith** | Private API + optional Blade admin | `http://monolith.test` |
| **monolith-gateway** | Public BFF, JWT cookie, CORS | `http://monolith-gateway.test` |
| **monolith-frontend** | Vue SPA | `http://localhost:5173` (`npm run dev`) or `http://localhost:8788` (`npm run preview:local`) |

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
| Locale persist (`users.locale`) | `GET /locale/{locale}` | `POST /api/v1/auth/locale` | Parity |

### Realtime notifications (SPA)

When Monolith has `APP_NOTIFICATION_TYPE=realtime` and a configured `BROADCAST_CONNECTION` (Reverb/Pusher), set on the frontend build:

```env
VITE_BROADCAST_ENABLED=true
VITE_PUSHER_APP_KEY=...
VITE_PUSHER_HOST=127.0.0.1
VITE_PUSHER_PORT=6001
VITE_PUSHER_SCHEME=http
```

Vite dev/preview proxies `/broadcasting` to the gateway, which forwards to Monolith `/broadcasting/auth` with the `auth_token` cookie.

If websocket auth fails, the bell falls back to HTTP polling (`VITE_NOTIFICATION_POLLING_INTERVAL`, default 60s) — same behaviour as Blade.

## SPA frontend implementation

Recent UX work (skeleton loading, responsive shell, auth layout parity) lives entirely in **monolith-frontend**. No gateway or Monolith API changes were required.

| Guide (monolith-frontend) | Topic |
|---------------------------|-------|
| `docs/audit-summary.md` | What changed in each of the three projects |
| `docs/architecture.md` | Stack, axios cookie auth, Vite proxy, folder layout |
| `docs/async-fetch-ux.md` | `useAsyncData`, `AppFetchState`, skeleton/error components |
| `docs/responsive-ui.md` | `useBreakpoint`, mobile layouts, DataTables Responsive |
| `docs/auth-layout.md` | AdminLTE auth card, locale switcher, mobile spacing |
| `docs/testing.md` | Vitest vs Playwright, 49 unit tests |

Path on disk: `monolith-frontend/docs/` (separate project directory from Monolith and Gateway).

## Production

- `GATEWAY_ENFORCE=true` on Monolith
- Shared strong `GATEWAY_SECRET` / `MONOLITH_GATEWAY_SECRET`
- `AUTH_COOKIE_SECURE=true` and `AUTH_COOKIE_SAME_SITE=none` on gateway
- `FRONTEND_URL` and `VITE_API_BASE_URL` must match deployed public origins (HTTPS)
