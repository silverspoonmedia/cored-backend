# Web frontend (removed)

The in-repo **Blade + AdminLTE 4 admin has been removed**. Monolith-Vue-Workers is the sole browser UI; it consumes `/api/v1/*` via cross-site HttpOnly cookies and CSRF double-submit — see [integration/vue-workers-spa.md](../integration/vue-workers-spa.md).

## What remains on the Laravel `web` stack

`routes/web.php` keeps **server endpoints only** (no views):

| Route | Purpose |
|-------|---------|
| `GET /` | Redirect to `SPA_URL` |
| `GET /s/f/{file}`, `/s/d/{folder}` | Public share (JSON or redirect to SPA) |
| `GET /payment/ipaymu/return\|cancel` | iPaymu browser return → SPA `/payment/return\|cancel` |
| `GET /apps/auth/discord/callback` | Discord OAuth callback → SPA paths |
| `routes/web/{domain}.php` | Optional product server endpoints (scaffolded by `domain:make`) |

JWT bridge middleware (`AuthenticateWithJwtCookie`, `RefreshJwtCookie`, `web.auth`, `web.guest`) and view composers are gone. API auth uses `AuthenticateWithSpaCookie` + `VerifySpaCsrf` on the `api` group.

Historical Blade patterns (partial manifest, UI conventions) lived here before removal; do not recreate them in Monolith — port UI to the SPA instead.
