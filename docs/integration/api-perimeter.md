# API perimeter (trusted proxy)

Monolith can optionally require a shared secret (or allowlisted IP) on API requests. This is an **in-app perimeter**, not a separate product repository.

Implementation: `App\Http\Middleware\EnsureGatewayRequest`, config `config/gateway.php`, env keys `GATEWAY_*`.

## Request paths

| Client | Path | Auth |
|--------|------|------|
| **Blade admin** | Browser → Monolith web → same-host `/api/v1/*` | HttpOnly JWT cookie → Bearer; local typically `GATEWAY_ENFORCE=false` |
| **Trusted proxy / external client** | Caller → Monolith `/api/v1/*` | Bearer JWT + `X-Gateway-Secret` when enforce is on |

Global API surface is **`GET /api/ping` only**. Product features live under `/api/v1/*`.

## Environment

| Variable | Purpose |
|----------|---------|
| `GATEWAY_ENFORCE` | When `true`, API requests must present a valid secret or allowlisted IP |
| `GATEWAY_SECRET` | Shared secret compared to header `X-Gateway-Secret` |
| `GATEWAY_ALLOWED_IPS` | Optional comma-separated IPs that bypass the secret header |

Local / tests: `GATEWAY_ENFORCE=false`. Production: `GATEWAY_ENFORCE=true` with a strong `GATEWAY_SECRET`.

Blade same-host calls (`ApiClient`) attach `X-Gateway-Secret` from config when a secret is configured so web controllers still work with enforce on.

## Production checklist

- `GATEWAY_ENFORCE=true` + strong `GATEWAY_SECRET`
- Secure session cookies; `JWT_SECRET` set
- `config:rebuild` after secret changes
- Liveness: `route:list --path=api/ping` shows `GET /api/ping`

## Related

- [platform/environment.md](../platform/environment.md) — env profiles and secrets
- [web/blade-admin.md](../web/blade-admin.md) — Blade JWT cookie bridge
- [domains/payment-gateway](../domains/payment-gateway/README.md) — payment domain (business gateways, not this perimeter)
