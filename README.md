# Monolith

Laravel 13 API application with **JWT authentication**, **TOTP two-factor authentication (2FA)**, **Spatie RBAC**, **hierarchical user management (H-RBAC)**, and a **versioned API** under `/api/v1`. Business endpoints live on versioned routes; a small global surface exists for health checks (`GET /api/ping`).

It also serves as the **API backend** for the sibling **Monolith-Vue-Workers** SPA ù the sole browser UI. The SPA consumes `/api/v1/*` via cross-site HttpOnly cookies and CSRF double-submit. Minimal server `web` routes remain for public share, payment return, and OAuth callbacks. See **[Web frontend (removed)](docs/web/blade-admin.md)** and **[Vue-Workers SPA](docs/integration/vue-workers-spa.md)**.

**Internal code layout:** Domain logic is grouped under `app/Domain/{Identity,Navigation,Notification,ActivityLog,PaymentGateway,Service,Archivd,ù}/` (logical monorepo inside this repository). See [Domain modules](docs/platform/architecture.md) (historical: [archive](docs/archive/)).

### Living documentation (required)

**This documentation is referenced when the matching area is in play.** Use `README.md` and the guides under [`docs/`](docs/) as the primary reference before changing that area. Do not load the entire `docs/` tree on every task.

**AI agents:** [AGENTS.md](AGENTS.md) is the always-on index. Open [docs/agents/core-rules.md](docs/agents/core-rules.md) and the owning platform/domain guide only when that rule is in play. Context budget: [docs/agents/context-budget.md](docs/agents/context-budget.md). Prefer [Monolith-Backend.code-workspace](Monolith-Backend.code-workspace); use [Monolith-Fullstack.code-workspace](Monolith-Fullstack.code-workspace) only when the API?SPA contract moves. Work stays in this repository (or the loaded workspace subset).

**All project documentation must be written in English** (README, `docs/`, and any new guides or sections you add).

---

## Identifiers (UUID)

This project does **not** use auto-increment integer primary keys for domain models. **Primary keys and foreign keys are UUID columns** end to end.

| Rule | Practice |
|------|----------|
| Primary key | `$table->uuid('id')->primary()` in migrations |
| Foreign key | `$table->foreignUuid('ù_id')->constrained(ù)` (or `uuid` + explicit FK) |
| Model | `Illuminate\Database\Eloquent\Concerns\HasUuids` ù Laravel 13 generates **UUID v7** on create |
| API / JWT | Route parameters, JSON `id` fields, and JWT `sub` are UUID strings |

**Already on UUID today**

| Area | Columns |
|------|---------|
| `users` | `id` (PK) |
| `sessions` | `user_id` ? `users.id` |
| Spatie (`roles`, `permissions`, pivots) | `id`, `model_id`, `role_id`, `permission_id` |
| Models | `App\Domain\Identity\Models\{User,Role,Permission}` and other domain models use `HasUuids` where applicable |

When you add a table that belongs to a user or another UUID-backed model, use `foreignUuid` (not `foreignId`). Do not pass integer IDs in validation or route binding. Full rationale, migration examples, and pitfalls (e.g. do not use DB `gen_random_uuid()` for v7) ? **[User primary keys (UUID v7)](docs/platform/identifiers.md)**.

---

## Requirements

| Tool | Version |
|------|---------|
| PHP | ^8.3 (extensions: `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`) |
| Composer | 2.x |
| Database | MySQL (default in profile templates), or PostgreSQL |

No Node.js toolchain is needed here: this repo has no frontend stack or `package.json`. The browser UI is the sibling `monolith-vue-workers` project.

On **Laragon** (Windows), enable PHP 8.3+ and create a MySQL database named `monolith` (or adjust `DB_DATABASE` in `.env`).

### Development OS (Windows)

**Active development targets Windows** (PowerShell / cmd). Shell examples in this repo assume a Windows environment unless noted otherwise.

**AI IDE / agent shells:** when chaining multiple commands in one line, use **`;`** as the separatorùnot **`&&`**. On Windows, `&&` often fails or behaves differently than on Unix shells, which wastes tokens on retries and error output. Prefer separate commands when clarity matters; when you must chain, use semicolons:

```powershell
cd monolith; php artisan migrate; php artisan db:seed
```

---

## Setup from scratch

### 1. Clone and install dependencies

```bash
git clone <repository-url> monolith
cd monolith

composer install
```

### 2. Environment file

```bash
php artisan env:install local
```

This copies `.env.local.example` ? `.env`, seeds a matching snapshot (`.env.local`), sets `.env.profile`, and generates `APP_KEY` and `JWT_SECRET` when empty. See [Environment configuration](docs/platform/environment.md) for staging/production profiles.

To swap between saved profiles later without losing values, use `php artisan env:switch {profile}` ù see [Switching profiles](docs/platform/environment.md#switching-profiles-local-dev).

Adjust at least:

| Variable | Notes |
|----------|--------|
| `APP_URL` | Base URL of the app (e.g. `http://monolith.test` on Laragon) |
| `DB_*` | MySQL connection (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) |
| `JWT_*` | `JWT_SECRET` is generated by `env:install`; re-run `php artisan jwt:secret` to rotate |
| `MAIL_*` | Required for email verification and password reset in real use (tests use `array` driver) |
| `BROADCAST_CONNECTION` / `PUSHER_*` | Optional locally. After `LocalSettingsSeeder`, local delivery defaults to `realtime` when Pusher is configured; otherwise the bell polls. Set **Settings ? Platform** `platform.notifications.delivery` plus credentials ù see [Activity notifications](docs/domains/notification/README.md#delivery) |
| `HCAPTCHA_*` | Optional locally (leave `HCAPTCHA_SECRET` / `HCAPTCHA_SITEKEY` empty). Enable via **Settings ? Platform** `platform.hcaptcha.enabled` and set keys from the [hCaptcha dashboard](https://dashboard.hcaptcha.com/) ù API: [JWT Authentication](docs/domains/identity/README.md#hcaptcha-defense-in-depth); SPA widget in `monolith-vue-workers` |

### 3. Database migrations and seeders

```bash
php artisan migrate
php artisan db:seed
```

`DatabaseSeeder` runs core/product role seeders, domain and notification settings, sample users/product activations, then the authoritative `MenuSeeder`. Every menu seed replaces all active and trashed menu rows and removes admin-created/custom menus; do not run `db:seed` merely to patch one menu entry. See [Navigation ù Authoritative seeding](docs/domains/navigation/README.md#authoritative-seeding).

Seeded sample users:

| Email | Password | Role | Purpose |
|-------|----------|------|---------|
| `admin@example.com` | `123456789` | Admin | Default sign-in |
| `manager@example.com` | `123456789` | Management | Management role demo |
| `finance@example.com` | `123456789` | Moderator | Moderator role demo |
| `user@example.com` | `123456789` | User | Regular user |

**Two-factor authentication (2FA):** Users can enable TOTP-based 2FA from their profile. Once enabled, login returns a `two_factor_token` (not a JWT); the client must call `POST /api/v1/auth/two-factor/login` with the token and a valid TOTP code to obtain the real JWT. See [JWT Authentication & RBAC](docs/domains/identity/README.md#two-factor-authentication-2fa) for full API and web flow.

To reset and reseed during development:

```bash
# Requires ALLOW_DESTRUCTIVE_DB=true in .env ù see docs/platform/database-safety.md
php artisan migrate:fresh --seed
```

**Database safety:** Tests use SQLite; dev uses MySQL. Prefer `composer test` (runs `config:clear` first). Bare `php artisan test` is guarded on current code ù see [docs/platform/database-safety.md](docs/platform/database-safety.md).

### 4. Frontend

None in this repo - the Blade admin and Vite asset pipeline were removed. The sole browser UI is the sibling `monolith-vue-workers` SPA, which consumes the `/api/v1/*` API directly. See [docs/integration/vue-workers-spa.md](docs/integration/vue-workers-spa.md).

### 5. Run the application

**API only:**

```bash
php artisan serve
```

**Full stack** (HTTP server, queue worker, log tail):

```bash
composer run dev
```

### One-shot setup (Composer script)

`composer.json` defines a `setup` script that installs PHP deps, runs `env:install local` when `.env` is missing, runs migrations. It does **not** run seedersùrun those after `composer run setup`:

```bash
composer run setup
php artisan db:seed
```

---

## Verify the install

| Check | Command / URL |
|-------|----------------|
| Health check | `GET /api/ping` (global) or `GET /whatsup` |
| Login | `POST /api/v1/auth/login` with `admin@example.com` / `123456789` |
| Authenticated profile | `GET /api/v1/auth/me` with `Authorization: Bearer <token>` |
| Tests | `composer test` (preferred; clears config cache first) |
| Notification bell (polling) | Sign in, open dashboard ù badge polls on load and every `platform.notifications.polling_interval` s (default 60; Settings delivery = `polling` or missing Pusher credentials) |
| Notification bell (realtime) | Set **Settings ? Platform** `platform.notifications.delivery` = `realtime` + `PUSHER_*`, run `npm run build`, trigger a notification (e.g. create user) ù badge updates without reload; see [Activity notifications](docs/domains/notification/README.md#delivery) |
| Code style | `php artisan pint` ù see [Laravel Pint](docs/platform/tooling.md) |

---

## Security

**Security Audit Report:** See [`SECURITY_AUDIT.md`](SECURITY_AUDIT.md) for the comprehensive OWASP Top 10:2025 security audit (20 findings, all critical and high priority vulnerabilities patched).

**Last Audit:** 2026-06-16 (Audit #1 - 19/20 findings resolved)

**Security Audit Procedure:** See [`docs/platform/security.md`](docs/platform/security.md) for repeatable audit methodology. Run security audits quarterly, before major deployments, or after significant feature changes.

**Production Security Checklist:**
- Set `APP_ENV=production` and `APP_DEBUG=false`
- Set `SESSION_SECURE_COOKIE=true` (enforce HTTPS-only cookies)
- Set `SESSION_ENCRYPT=true` (encrypt session data)
- Verify `JWT_SECRET` is generated and strong
- Enable **Settings ? Platform** `platform.hcaptcha.enabled` with valid `HCAPTCHA_SECRET` and `HCAPTCHA_SITEKEY` (defense-in-depth on public auth endpoints)

---

## Project documentation

All guides live under [`docs/`](docs/). Read them in this order when onboarding:

### API surface and structure

| Document | Description |
|----------|-------------|
| [Domain modules](docs/platform/architecture.md) | Core/locked/removable domains, `domain:list\|make\|remove`, layout |
| [Internal monorepo assessment](docs/archive/internal-monorepo-assessment.md) | Historical: why logical monorepo, domain map, phases (archive only) |
| [Internal monorepo porting](docs/archive/internal-monorepo-porting.md) | Implementation reference: migration order, scripts, gotchas, verification |
| [API routing](docs/platform/api-routing.md) | Global (`/api/*`) vs versioned (`/api/v1/*`) routes, conventions, and where to add endpoints |
| [API response envelope](docs/platform/api-envelope.md) | Canonical JSON envelope, error mapping (401/403/404/419/422/429), `/batch` partial-failure contract |
| [Artisan commands](docs/platform/commands.md) | Project command index (env, domains, payments, vtual, microstock, cache, i18n) |
| [CRUD architecture & repository pattern](docs/platform/crud.md) | Layered CRUD flow, `EloquentCrudRepository` vs dedicated repositories, `Route::apiResource` for simple resources |
| [Payment gateways](docs/domains/payment-gateway/README.md) | Midtrans/iPaymu, invoices, channel checkout, subscriptions, webhooks |
| [Archivd](docs/domains/archivd/README.md) | Product domain: nested folders, chunk S3 upload, link share |
| [Database safety](docs/platform/database-safety.md) | Prevent dev DB wipe; test vs MySQL; `composer test`; recovery |
| [List display modes & lookups](docs/platform/list-display-modes.md) | `display_mode=query` list/pagination contract, paginated `/api/v1/lookups/*`, and the retained-but-unused Yajra `datatable` mode |
| [Dynamic navigation menu (hierarchy + RBAC)](docs/domains/navigation/README.md) | Self-referential menu tree, per-viewer visibility, ordering, and the dedicated `MenuRepository` |
| [Activity notifications (per role + per user)](docs/domains/notification/README.md) | Generic event-driven notification engine: per-type audiences in **DB via seeder** (hybrid with code catalogue), per-user read state, navbar bell with polling or realtime websocket |
| [Activity notifications ù role vs user handling](docs/domains/notification/README.md) | Reference for broadcast (per role) vs personal (per user) targeting, lifecycle, pitfalls, and flow-change decisions |
| [Activity notifications ù implementation guide](docs/domains/notification/README.md) | **AI-agent playbook:** step-by-step checklists to add types, wire dispatch, change audience, translations, tests, and DataTable reload (hybrid enum + dynamic audience) |
| [Activity log (audit trail)](docs/domains/activity-log/README.md) | Spatie laravel-activitylog v5: immutable mutation audit (`activity_log`), automatic model logging, role-assignment events, API viewer + SPA screens |
| [Authentication activity assessment](docs/archive/auth-activity-assessment.md) | Auth event coverage matrix: login/logout/2FA/password ? activity log vs file log, gaps and security notes |
| [Web frontend (removed)](docs/web/blade-admin.md) | Blade admin removed; Monolith-Vue-Workers SPA is sole UI ù stub documents remaining server `web` endpoints |

### Authentication and authorization

| Document | Description |
|----------|-------------|
| [JWT authentication & RBAC](docs/domains/identity/README.md) | JWT guard, register/login/refresh, Spatie roles & permissions, env config, local setup |
| [User management & H-RBAC](docs/domains/identity/README.md) | `/api/v1/users` CRUD, role hierarchy (`roles.level`), policies, permissions matrix |
| [User primary keys (UUID v7)](docs/platform/identifiers.md) | Why UUID v7 on `users`, migrations, and applying the same pattern to new models |

### Localization

| Document | Description |
|----------|-------------|
| [Localization (multi-language)](docs/platform/localization.md) | Cookie/user locale resolution, `laravel-lang/common` file packs, Spatie translatable models, `lang/` layout, validation strategy, `curated at` flag convention, per-locale curation coverage |

### Tooling

| Document | Description |
|----------|-------------|
| [Laravel Pint (Artisan wrapper)](docs/platform/tooling.md) | `php artisan pint` instead of calling the binary directly |

### Performance and deployment

| Document | Description |
|----------|-------------|
| [Environment configuration](docs/platform/environment.md) | Profile templates, `env:install`, `env:switch`, and production checklist |
| [Octane / Swoole Readiness](docs/platform/octane.md) | Rules and patterns for long-lived worker safety (mandatory before deploying Octane + Swoole) |
### Cross-links between docs

- Auth endpoints and middleware ? [jwt-authentication.md](docs/domains/identity/README.md)
- User CRUD and hierarchy rules ? [user-management-hrbac.md](docs/domains/identity/README.md) (builds on JWT doc)
- Route placement for new features ? [api-routing.md](docs/platform/api-routing.md)
- Simple CRUD scaffolding (`Route::apiResource` + repository pattern) ? [crud-architecture.md](docs/platform/crud.md)
- Dual-mode list/index (query JSON vs DataTables + RBAC actions) ? [list-display-modes.md](docs/platform/list-display-modes.md)
- Hierarchical, RBAC-filtered navigation ? [navigation-menu.md](docs/domains/navigation/README.md)
- Role-targeted + personal activity notifications (events ? roles/users) ? [notifications.md](docs/domains/notification/README.md)
- Broadcast vs personal targeting (role vs user lifecycle reference) ? [notifications-role-vs-user.md](docs/domains/notification/README.md)
- Adding or changing a notification type (agent checklist) ? [notifications-implementation-guide.md](docs/domains/notification/README.md)
- Immutable mutation audit trail (Spatie activitylog) ? [activity-log.md](docs/domains/activity-log/README.md)
- Auth sessions and password events in activity log ? [auth-activity-assessment.md](docs/archive/auth-activity-assessment.md)
- ID format for users and Spatie models ? [user-uuid-v7.md](docs/platform/identifiers.md)
- Monolith-Vue-Workers SPA (sole UI) ? [vue-workers-spa.md](docs/integration/vue-workers-spa.md)
- Archivd direct-upload (chunked S3) ? [archivd README](docs/domains/archivd/README.md)
- hCaptcha on public auth (API validation + SPA widget) ? [identity README](docs/domains/identity/README.md#hcaptcha)
- Multi-language (API, SPA, DB) ? [localization.md](docs/platform/localization.md)
- Payments, channel checkout, invoices ? [payment-gateways.md](docs/domains/payment-gateway/README.md)
- Database safety (tests vs MySQL, `ALLOW_DESTRUCTIVE_DB`) ? [database-safety.md](docs/platform/database-safety.md)
- Long-lived worker safety for future Octane + Swoole deployment ? [octane-readiness.md](docs/platform/octane.md)

---

## Common Artisan commands

```bash
php artisan env:install local    # Scaffold .env from local profile (see docs/platform/environment.md)
php artisan env:switch staging   # Swap active .env; keep per-profile snapshots
php artisan env:switch           # Show active profile
php artisan key:generate         # Generate APP_KEY when empty
php artisan key:generate --force # Rotate APP_KEY; retired key moves to APP_PREVIOUS_KEYS
php artisan migrate              # Apply migrations
php artisan db:seed              # Roles, permissions, sample admin
php artisan jwt:secret           # Generate JWT_SECRET (once per environment)
php artisan lang:update          # Refresh laravel-lang package files (also runs on composer update)
php artisan locale:add <locale>  # Onboard a new locale (managed files + config + app-owned scaffolding)
php artisan pint                 # Format PHP code
composer test                    # PHPUnit (clears config cache first)
```

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
