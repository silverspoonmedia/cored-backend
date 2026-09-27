# Monolith

Laravel 13 API application with **JWT authentication**, **TOTP two-factor authentication (2FA)**, **Spatie RBAC**, **hierarchical user management (H-RBAC)**, and a **versioned API** under `/api/v1`. Business endpoints live on versioned routes; the global surface is health checks (`GET /api/ping`, `GET /whatsup`).

It is the **API backend** for the sibling **Monolith-Vue-Workers** SPA — the sole browser UI — which consumes `/api/v1/*` cross-site with HttpOnly cookies and CSRF double-submit. Minimal server `web` routes remain for public share, payment return, and OAuth callbacks. Domain logic is grouped under `app/Domain/{Identity,Navigation,Notification,ActivityLog,PaymentGateway,Service,Archivd,Vtual,Microstock,...}/`.

## Documentation

| Need | Open |
|------|------|
| Agent pakem (always-on) | [AGENTS.md](AGENTS.md) — the `.cursor/rules/*.mdc` file for the area |
| Doc map, placement rules, brief template | [docs/README.md](docs/README.md) — [.cursor/rules/docs-pakem.mdc](.cursor/rules/docs-pakem.mdc) |
| Task scope tags (`[fullstack]`/`[backend]`/`[frontend]`) | [.cursor/rules/task-scope.mdc](.cursor/rules/task-scope.mdc) |
| API + SPA contract | [docs/integration/vue-workers-spa.md](docs/integration/vue-workers-spa.md) (open only when the contract moves) |
| History, audits, progress trackers | [docs/archive/](docs/archive/) |

All documentation is written in **English**. Update the owning doc and rule file in the same task; the keyword `wrap up the docs` triggers a session-wide consolidation.

## Requirements

| Tool | Version |
|------|---------|
| PHP | ^8.4 (extensions: `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`) |
| Composer | 2.x |
| Database | MySQL (default in profile templates), or PostgreSQL |

PHP 8.4, not 8.3: the locked `silverspoonmedia/pdf-tools` (dev-main) and `symfony/*` 8.1 require `^8.4`, so `composer install` refuses the lock file on 8.3. The same pin is in [.github/workflows/ci.yml](.github/workflows/ci.yml).

No Node.js toolchain is needed here — this repo has no frontend stack or `package.json`. On **Laragon** (Windows), enable PHP 8.4+ and create a MySQL database named `monolith` (or adjust `DB_DATABASE`).

**Windows shells:** chain commands with `;`, not `&&` (PowerShell rejects `&&` on the shipped versions). Example: `cd monolith; php artisan migrate; php artisan db:seed`.

## Setup

```bash
composer run setup          # deps + env:install local (when .env is missing) + migrate
php artisan db:seed         # roles, permissions, settings, menus, sample users
```

Adjust at least `APP_URL` and `DB_*` in `.env`. Environment profiles, secrets vs `system_settings`, and the `curate the env` routine: [docs/platform/environment.md](docs/platform/environment.md) + [docs/platform/configuration-policy.md](docs/platform/configuration-policy.md).

Seeded sample users: `admin@example.com` (Admin) — `manager@example.com` (Management) — `finance@example.com` (Moderator) — `user@example.com` (User) — password `123456789`.

```bash
php artisan serve           # API only
composer run dev            # HTTP server + queue worker + log tail
```

`php artisan db:seed` replaces **all** menu rows (the authoritative `MenuSeeder`); never run it to patch one menu entry — see [navigation README](docs/domains/navigation/README.md).

## Verify the install

| Check | Command / URL |
|-------|----------------|
| Health check | `GET /api/ping` or `GET /whatsup` |
| Login | `POST /api/v1/auth/login` with `admin@example.com` / `123456789` |
| Authenticated profile | `GET /api/v1/auth/me` |
| Tests | `composer test` (preferred; clears config cache first) |
| Code style | `php artisan pint` — see [docs/platform/tooling.md](docs/platform/tooling.md) |

## Security

Audit history and open findings: [docs/archive/security-audit-2026-06-16.md](docs/archive/security-audit-2026-06-16.md). Repeatable procedure: [docs/platform/security.md](docs/platform/security.md) — run quarterly, before major deployments, or after significant feature changes. Response data minimization rules for every JSON Resource: [docs/platform/reference-security.md#response-data-minimization-mandatory](docs/platform/reference-security.md#response-data-minimization-mandatory).

Production checklist:

- `APP_ENV=production` and `APP_DEBUG=false`
- `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`
- `JWT_SECRET` generated and strong
- hCaptcha enabled with valid keys (`platform.hcaptcha.enabled`)
- API origin reachable only through the Cloudflare Tunnel (see [docs/platform/security.md](docs/platform/security.md))

## Common commands

```bash
php artisan env:install local    # scaffold .env from the local profile
php artisan env:switch staging   # swap the active .env profile
php artisan key:generate --force # rotate APP_KEY (previous key retired)
php artisan migrate              # apply migrations
php artisan jwt:secret           # generate JWT_SECRET
php artisan pint                 # format PHP
composer test                    # PHPUnit (sqlite in-memory)
```

Never run `migrate:fresh`, `migrate:refresh`, or `db:wipe` on a non-sqlite connection without `ALLOW_DESTRUCTIVE_DB=true` — see [docs/platform/database-safety.md](docs/platform/database-safety.md).

## License

Laravel is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
