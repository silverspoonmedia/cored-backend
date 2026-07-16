# Three-project integration (Monolith + Gateway + Vue)

Agent scope: [agents/guide.md](../agents/guide.md). HTTP contract changes → [changelog](../changelog/README.md) `pending/` — implement consumers only in **`downstream sync session`**.

| Project | Role | Local URL |
|---------|------|-----------|
| **monolith** | Private API + Blade | `http://monolith.test` |
| **monolith-gateway** | Public BFF, cookie, CORS | `http://monolith-gateway.test` |
| **monolith-frontend** | Vue SPA | `:5173` dev / `:8788` preview |

## Adaptation matrix

| Monolith change | Gateway | Frontend |
|-----------------|---------|----------|
| Namespace only / same URL | — | — |
| API URL or JSON shape | Proxy/transform | `src/api/*` |
| Route names (menus/DataTable) | `vue-routes.php` | router |

## Local wiring

1. Monolith: `GATEWAY_ENFORCE=false`, shared secret for when enforce on  
2. Gateway: `MONOLITH_BASE_URL=http://monolith.test`, matching secret, `FRONTEND_URL`  
3. Frontend: empty `VITE_API_BASE_URL` → Vite proxies `/api` + `/broadcasting` to gateway  

Do not point SPA at `localhost:8000` unless gateway actually serves there. Seeded login: `admin@example.com` / `123456789`.

| Mode | SPA URL | CORS note |
|------|---------|-----------|
| `npm run dev` | `:5173` | Same-origin via proxy |
| `preview:local` | `:8788` | Match `FRONTEND_URL` |

Proxy rewrites cookie domain to `localhost`.

## Two client paths

| Client | Path | Auth |
|--------|------|------|
| Vue | Browser → Gateway → Monolith | HttpOnly cookie on gateway → Bearer upstream |
| Blade | Browser → Monolith web → same-host API | Cookie → Bearer; enforce off locally |

## Playground

`/api/playground/*` global (not v1); off in production. Gateway catch-all proxies same path.

## Gateway is stateless

**No `DB_*` on gateway** (Laragon shared PHP workers can pollute Monolith env). Auth DB only on Monolith. Session/cache/queue on gateway: file/sync.

SQLite error with `Database: monolith` → often stale root `monolith` file or gateway `DB_*` leak — clear, use MySQL, restart Apache.

## SPA vs Blade parity

Auth, users, menus, orders playground, activity logs, notifications, shell — aligned. Differences: locale switch surface (Blade `GET /locale/{locale}` vs SPA `POST /auth/locale` + client cookie); payment Snap UI **Vue only** (Blade channel-only).

### Realtime (SPA)

Monolith `APP_NOTIFICATION_TYPE=realtime` + frontend `VITE_BROADCAST_*` / Pusher vars. Websocket **direct** to Pusher host; channel auth via Gateway → Monolith `/broadcasting/auth`. Failure → polling.

### hCaptcha (SPA)

When Monolith captcha on: `VITE_HCAPTCHA_ENABLED` + sitekey on Frontend build.

## Aggregates (Gateway)

| Endpoint | Replaces |
|----------|----------|
| `GET /api/v1/bootstrap` | me + nav trees + unread |
| `GET /api/v1/users/{id}/edit-context` | roles + user + activity |
| `GET /api/v1/navigation/menus/form-options` | menu record |
| `GET /api/v1/lookups/*` | Select2 pages |

Monolith APIs stay granular. Gateway docs (sync session only): architecture, auth-cookie, aggregates, programmatic API access, configuration.

## Production

- `GATEWAY_ENFORCE=true` + strong shared secret  
- Secure cookie / SameSite on gateway  
- `VITE_API_BASE_URL` = gateway HTTPS (not Pages origin)  
- Frontend static only — no Pages Functions for BFF  

Cross-repo tests: `.\scripts\test-workspace.ps1`.

## Related

- [platform/environment.md](../platform/environment.md)
- [web/blade-admin.md](../web/blade-admin.md)
- [domains/payment-gateway](../domains/payment-gateway/README.md)
