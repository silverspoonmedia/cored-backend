# Monolith

Laravel 13 API application with **JWT authentication**, **TOTP two-factor authentication (2FA)**, **Spatie RBAC**, **hierarchical user management (H-RBAC)**, and a **versioned API** under `/api/v1`. Business endpoints live on versioned routes; a small global surface exists for health checks and playground CRUD.

It also ships a server-rendered **Blade admin frontend** (AdminLTE 4 / Bootstrap 5) that is a **pure consumer of the versioned API**: every web controller forwards to `/api/v1/*`, so validation, CRUD, and RBAC run **only once — in the API** (no duplicated business logic). The browser authenticates with the **same stateless JWT, carried in an HttpOnly cookie**, which the web layer forwards to the API as a Bearer token. See **[Web Frontend (Blade + AdminLTE 4)](docs/web/blade-admin.md)**.

A separate **Vue SPA** (`monolith-frontend` on Cloudflare Pages) can mirror the Blade UI via the public **monolith-gateway** BFF. Gateway proxies `/api/*` to this application, sets HttpOnly cookies for the SPA, and enforces `GATEWAY_SECRET` when `GATEWAY_ENFORCE=true`. See [Three-project integration (Monolith + Gateway + Vue)](docs/integration/spa-gateway.md) and [Environment configuration — Public gateway perimeter](docs/platform/environment.md#public-gateway-perimeter). SPA guides: `monolith-frontend/docs/`. Gateway internals: `monolith-gateway/docs/`.

**Internal code layout:** Domain logic is grouped under `app/Domain/{Identity,Navigation,Notification,ActivityLog,Playground,PaymentGateway,Service,FileHosting,…}/` (logical monorepo inside this repository). See [Domain modules](docs/platform/architecture.md) (historical: [archive](docs/archive/)). Gateway and Frontend stay separate repos; HTTP contract changes are logged in the [changelog](docs/changelog/README.md) (`pending/` → `done/`) and synced in a **`downstream sync session`**.

### Living documentation (required)

**This documentation is referenced on every task.** Use `README.md` and the guides under [`docs/`](docs/) as the primary reference before changing code, adding features, or completing work. **AI agents:** read [AI Agent Guide](docs/agents/guide.md) and root [AGENTS.md](AGENTS.md) on every task; daily work stays in Monolith only. Changes that affect Gateway or Frontend HTTP contracts are logged in [docs/changelog/](docs/changelog/README.md) (`pending/`) and implemented in a separate **`downstream sync session`** — not in every Monolith task. Conflicting pending entries are resolved with **`sync consolidation`** (newest wins; else verify Monolith code). To **close a Monolith session** (improve docs for work done, then write one changelog entry with Delegation), the prompt must contain the exact keyword **`upstream documentation session`** — see [Changelog](docs/changelog/README.md). Without that keyword, treat the session as still in the work phase. **Always update the relevant documentation** (README and/or files in `docs/`) when your changes affect setup, conventions, the API, auth, the data schema, localization, or any behavior described here—do not let code and docs drift apart.

**Documentation placement (required):** Living docs are organized **by domain** (mirror `app/Domain/*`) plus `platform/`, `web/`, `integration/`, and `agents/`. When adding or expanding documentation, put content in the matching section — **do not** create a new subject file at `docs/*.md` root. Prefer editing an existing guide; use `docs/archive/` only for historical assessments. Full rules: [docs/README.md — Rules for new documentation](docs/README.md#rules-for-new-documentation-mandatory).

**All project documentation must be written in English** (README, `docs/`, and any new guides or sections you add).

**Localization (required on every task that touches copy):** Any change that adds or modifies user-facing text — Blade labels, API `message` strings, validation/policy errors, flash/AJAX copy, or database content shown to users — must include translations for **all** supported locales (`en`, `id`, `ja` today) in the **same task/PR**. Use `__('…')` with application-owned files under `lang/{locale}/` (never hardcode strings), or Spatie translatable fields for database content. See [Localization](docs/platform/localization.md#task-rule-required) for the full checklist and conventions.

**Octane / Swoole readiness (required on every task):** All new code must follow the mandatory patterns in [Octane / Swoole Readiness](docs/platform/octane.md) to survive long-lived worker processes. Never store request/user state on static properties or singletons. Never mutate facade/guard state without cleanup. Never call `env()` outside config files. Never use `config()->set()` at runtime. This project is not yet running Octane, but every line of code must be written as if it will tomorrow.

**Full-stack implementation (required on every Monolith task):** Every feature or change must be implemented end to end across **all relevant Monolith layers** in the **same task/PR** — do **not** ship work that only touches a single layer. This means API + Web + Blade + translations inside **this repository**; it does **not** require updating Gateway or Frontend in the same task (use the [changelog](docs/changelog/README.md) when HTTP contracts change). At minimum, a typical task should cover:

| Layer | What to touch |
|-------|----------------|
| **API** | Versioned routes (`/api/v1/*`), controllers, form requests, policies, repositories, and API responses |
| **Web** | Web controllers that consume the API (`ConsumesApi` / `ApiClient`), routes in `routes/web.php` and `routes/web/*.php`, and request forwarding |
| **Blade views** | Screens, forms, list tables, partials, and reusable components under `resources/views/{layout}/` (default layout: `adminlte4` via `WEB_LAYOUT` / `config/web.php`; see [web-frontend.md](docs/web/blade-admin.md#layout-namespace-and-configwebphp)) |
| **Translation** | All user-facing strings in `lang/{locale}/` for every supported locale (`en`, `id`, `ja`) |
| **Other (as applicable)** | Migrations, seeders, models, events/listeners, notifications, navigation menu entries, tests, and the relevant `docs/` guides |

A task that adds an API endpoint without the matching web route, Blade screen, and translations is **incomplete**. Likewise, a Blade-only or API-only change without its counterpart layers is not acceptable. See [Web frontend](docs/web/blade-admin.md), [CRUD architecture](docs/platform/crud.md), and [Localization](docs/platform/localization.md) for layer-specific conventions.

---

## Identifiers (UUID)

This project does **not** use auto-increment integer primary keys for domain models. **Primary keys and foreign keys are UUID columns** end to end.

| Rule | Practice |
|------|----------|
| Primary key | `$table->uuid('id')->primary()` in migrations |
| Foreign key | `$table->foreignUuid('…_id')->constrained(…)` (or `uuid` + explicit FK) |
| Model | `Illuminate\Database\Eloquent\Concerns\HasUuids` — Laravel 13 generates **UUID v7** on create |
| API / JWT | Route parameters, JSON `id` fields, and JWT `sub` are UUID strings |

**Already on UUID today**

| Area | Columns |
|------|---------|
| `users` | `id` (PK) |
| `sessions` | `user_id` → `users.id` |
| Spatie (`roles`, `permissions`, pivots) | `id`, `model_id`, `role_id`, `permission_id` |
| `order_master` / `order_detail` | `id` (PK), `order_detail.order_master_id` (FK) |
| `attachments` | `id` (PK), `attachable_id` (polymorphic FK via `uuidMorphs`), `user_id` (FK → `users`) |
| Models | `App\Domain\Identity\Models\{User,Role,Permission}`, `App\Domain\Playground\Models\{Order,OrderDetail,Attachment}` use `HasUuids` |

When you add a table that belongs to a user or another UUID-backed model, use `foreignUuid` (not `foreignId`). Do not pass integer IDs in validation or route binding. Full rationale, migration examples, and pitfalls (e.g. do not use DB `gen_random_uuid()` for v7) → **[User primary keys (UUID v7)](docs/platform/identifiers.md)**.

---

## Requirements

| Tool | Version |
|------|---------|
| PHP | ^8.3 (extensions: `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`) |
| Composer | 2.x |
| Node.js & npm | For Vite frontend assets (optional if you only use the API) |
| Database | MySQL (default in profile templates), or PostgreSQL |

On **Laragon** (Windows), enable PHP 8.3+ and create a MySQL database named `monolith` (or adjust `DB_DATABASE` in `.env`).

### Development OS (Windows)

**Active development targets Windows** (PowerShell / cmd). Shell examples in this repo assume a Windows environment unless noted otherwise.

**AI IDE / agent shells:** when chaining multiple commands in one line, use **`;`** as the separator—not **`&&`**. On Windows, `&&` often fails or behaves differently than on Unix shells, which wastes tokens on retries and error output. Prefer separate commands when clarity matters; when you must chain, use semicolons:

```powershell
cd monolith; php artisan migrate; php artisan db:seed
```

**AI Agent token optimization:** Some AI models implement strict prompt caching and may repeatedly mention token optimization or usage statistics in their responses. If your AI agent exhibits this behavior (e.g., frequently commenting on token consumption, suggesting cache optimization strategies unprompted, or adding token-related warnings to routine operations), instruct it to omit unnecessary optimization commentary unless token usage is directly relevant to the current task. The agent should focus on delivering requested functionality without meta-commentary about the interaction itself—nagging about tokens wastes more tokens than it saves.

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

This copies `.env.local.example` → `.env`, seeds a matching snapshot (`.env.local`), sets `.env.profile`, and generates `APP_KEY` and `JWT_SECRET` when empty. See [Environment configuration](docs/platform/environment.md) for staging/production profiles.

To swap between saved profiles later without losing values, use `php artisan env:switch {profile}` — see [Switching profiles](docs/platform/environment.md#switching-profiles-local-dev).

Adjust at least:

| Variable | Notes |
|----------|--------|
| `APP_URL` | Base URL of the app (e.g. `http://monolith.test` on Laragon) |
| `DB_*` | MySQL connection (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) |
| `JWT_*` | `JWT_SECRET` is generated by `env:install`; re-run `php artisan jwt:secret` to rotate |
| `MAIL_*` | Required for email verification and password reset in real use (tests use `array` driver) |
| `BROADCAST_CONNECTION` / `PUSHER_*` | Optional locally (bell uses polling). For realtime, set `APP_NOTIFICATION_TYPE=realtime` plus Pusher or Socket-compatible credentials — see [Activity notifications](docs/domains/notification/README.md#realtime-vs-polling) |
| `APP_NOTIFICATION_TYPE` | `polling` (default) or `realtime` — controls navbar bell delivery mode |
| `APP_NOTIFICATION_POLLING_INTERVAL` | Polling badge refresh interval in seconds (default `60`; only used in polling mode) |
| `HCAPTCHA_*` | Optional locally (`HCAPTCHA_ENABLED=false`). In production, set `HCAPTCHA_ENABLED=true` plus `HCAPTCHA_SECRET` and `HCAPTCHA_SITEKEY` from the [hCaptcha dashboard](https://dashboard.hcaptcha.com/) — API: [JWT Authentication](docs/domains/identity/README.md#hcaptcha-defense-in-depth); Blade UI: [Web frontend](docs/web/blade-admin.md#hcaptcha-on-auth-forms) |

### 3. Database migrations and seeders

```bash
php artisan migrate
php artisan db:seed
```

`DatabaseSeeder` runs `RoleSeeder` (roles, permissions, hierarchy levels), `NotificationTypeSettingSeeder`, sample users, and navigation menus:

| Email | Password | Role | Purpose |
|-------|----------|------|---------|
| `admin@example.com` | `123456789` | Admin | Default sign-in |
| `manager@example.com` | `123456789` | Management | Management role demo |
| `finance@example.com` | `123456789` | Moderator | Moderator role demo |
| `user@example.com` | `123456789` | User | Regular user |

**Two-factor authentication (2FA):** Users can enable TOTP-based 2FA from their profile. Once enabled, login returns a `two_factor_token` (not a JWT); the client must call `POST /api/v1/auth/two-factor/login` with the token and a valid TOTP code to obtain the real JWT. See [JWT Authentication & RBAC](docs/domains/identity/README.md#two-factor-authentication-2fa) for full API and web flow.

To reset and reseed during development:

```bash
# Requires ALLOW_DESTRUCTIVE_DB=true in .env — see docs/platform/database-safety.md
php artisan migrate:fresh --seed
```

**Database safety:** Tests use SQLite; dev uses MySQL. Prefer `composer test` (runs `config:clear` first). Bare `php artisan test` is guarded on current code — see [docs/platform/database-safety.md](docs/platform/database-safety.md).

### 4. Frontend assets

Required for the **notification bell** (Vite bundle with Laravel Echo + Pusher). AdminLTE/Bootstrap still load from CDN; only the bell uses Vite.

```bash
npm install
npm run build
```

`package.json` includes `laravel-echo` and `pusher-js` as runtime dependencies. Vite entries are declared in `vite.config.js` (`resources/js/app.js`, `resources/js/notifications/bell.js`, `resources/js/payments/checkout.js`, `resources/js/payments/channels-admin.js`, `resources/css/payments/order-invoice-show.css`, and others).

For local development with hot reload:

```bash
npm run dev
```

### 5. Run the application

**API only:**

```bash
php artisan serve
```

**Full stack** (HTTP server, queue worker, log tail, Vite):

```bash
composer run dev
```

### One-shot setup (Composer script)

`composer.json` defines a `setup` script that installs PHP deps, runs `env:install local` when `.env` is missing, runs migrations, and builds frontend assets. It does **not** run seeders—run those after `composer run setup`:

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
| Notification bell (polling) | Sign in, open dashboard — badge polls on load and every `APP_NOTIFICATION_POLLING_INTERVAL` s (default 60; `APP_NOTIFICATION_TYPE=polling` or missing Pusher credentials) |
| Notification bell (realtime) | Set `APP_NOTIFICATION_TYPE=realtime` + `PUSHER_*`, run `npm run build`, trigger a notification (e.g. create user) — badge updates without reload; see [notifications.md](docs/domains/notification/README.md#realtime-vs-polling) |
| Code style | `php artisan pint` — see [Laravel Pint](docs/platform/tooling.md) |

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
- Set `HCAPTCHA_ENABLED=true` with valid `HCAPTCHA_SECRET` and `HCAPTCHA_SITEKEY` (defense-in-depth on public auth endpoints)
- Playground routes are automatically disabled in production

---

## Project documentation

All guides live under [`docs/`](docs/). Read them in this order when onboarding:

### API surface and structure

| Document | Description |
|----------|-------------|
| [Domain modules](docs/platform/architecture.md) | Core/locked/removable domains, `domain:list\|make\|remove`, layout |
| [Internal monorepo assessment](docs/archive/internal-monorepo-assessment.md) | Why logical monorepo, domain map, phases, Gateway/Frontend contract |
| [Internal monorepo porting](docs/archive/internal-monorepo-porting.md) | Implementation reference: migration order, scripts, gotchas, verification |
| [API routing](docs/platform/api-routing.md) | Global (`/api/*`) vs versioned (`/api/v1/*`) routes, conventions, and where to add endpoints |
| [CRUD architecture & repository pattern](docs/platform/crud.md) | Layered CRUD flow, `EloquentCrudRepository` vs dedicated repositories, `Route::apiResource` for simple resources, playground example, and the master-detail aggregate (Order + OrderDetail) with transactional writes |
| [Playground (reference surface)](docs/domains/playground/README.md) | Non-production `/api/playground/*` demos: simple CRUD (Users), master-detail Orders with **browser-direct chunked S3 uploads** (manual Start queue + configurable concurrency), and transactional writes |
| [Payment gateways](docs/domains/payment-gateway/README.md) | Midtrans/iPaymu, invoices, channel checkout, subscriptions, webhooks |
| [Database safety](docs/platform/database-safety.md) | Prevent dev DB wipe; test vs MySQL; `composer test`; recovery |
| [List display modes (query & DataTables)](docs/web/ui-patterns.md) | Dual-mode list/index responses, Yajra DataTables integration, RBAC action buttons for Bootstrap 5 / jQuery 3.7 |
| [Dynamic navigation menu (hierarchy + RBAC)](docs/domains/navigation/README.md) | Self-referential menu tree, per-viewer visibility, ordering, and the dedicated `MenuRepository` |
| [Activity notifications (per role + per user)](docs/domains/notification/README.md) | Generic event-driven notification engine: per-type audiences in **DB via seeder** (hybrid with code catalogue), per-user read state, navbar bell with polling or realtime websocket |
| [Activity notifications — role vs user handling](docs/domains/notification/README.md) | Reference for broadcast (per role) vs personal (per user) targeting, lifecycle, pitfalls, and flow-change decisions |
| [Activity notifications — implementation guide](docs/domains/notification/README.md) | **AI-agent playbook:** step-by-step checklists to add types, wire dispatch, change audience, translations, tests, and DataTable reload (hybrid enum + dynamic audience) |
| [Activity log (audit trail)](docs/domains/activity-log/README.md) | Spatie laravel-activitylog v5: immutable mutation audit (`activity_log`), automatic model logging, role-assignment events, API viewer + Blade screens |
| [Authentication activity assessment](docs/archive/auth-activity-assessment.md) | Auth event coverage matrix: login/logout/2FA/password → activity log vs file log, gaps and security notes |
| [Web frontend (Blade + AdminLTE 4)](docs/web/blade-admin.md) | Server-rendered admin UI that **consumes the versioned API** (web = API client); JWT-in-HttpOnly-cookie auth forwarded as Bearer, reusable Blade components, `MonolithApi` AJAX error handling, order direct-upload widget, user & menu management screens |

### Authentication and authorization

| Document | Description |
|----------|-------------|
| [JWT authentication & RBAC](docs/domains/identity/README.md) | JWT guard, register/login/refresh, Spatie roles & permissions, env config, local setup |
| [User management & H-RBAC](docs/domains/identity/README.md) | `/api/v1/users` CRUD, role hierarchy (`roles.level`), policies, permissions matrix |
| [User primary keys (UUID v7)](docs/platform/identifiers.md) | Why UUID v7 on `users`, migrations, and applying the same pattern to new models |

### Localization

| Document | Description |
|----------|-------------|
| [Localization (multi-language)](docs/platform/localization.md) | Cookie/user locale resolution, `laravel-lang/common` file packs, Spatie translatable models, `lang/` layout, and validation strategy |

### Tooling

| Document | Description |
|----------|-------------|
| [Laravel Pint (Artisan wrapper)](docs/platform/tooling.md) | `php artisan pint` instead of calling the binary directly |

### Performance and deployment

| Document | Description |
|----------|-------------|
| [Environment configuration](docs/platform/environment.md) | Profile templates, `env:install`, `env:switch`, and production checklist |
| [Octane / Swoole Readiness](docs/platform/octane.md) | Rules and patterns for long-lived worker safety (mandatory before deploying Octane + Swoole) |
| [Three-project integration (Monolith + Gateway + Vue)](docs/integration/spa-gateway.md) | Local Laragon wiring, gateway cookie auth, SPA vs Blade parity, production checklist; gateway internals in `monolith-gateway/docs/` |

### Cross-links between docs

- Auth endpoints and middleware → [jwt-authentication.md](docs/domains/identity/README.md)
- User CRUD and hierarchy rules → [user-management-hrbac.md](docs/domains/identity/README.md) (builds on JWT doc)
- Route placement for new features → [api-routing.md](docs/platform/api-routing.md)
- Simple CRUD scaffolding (`Route::apiResource` + repository pattern) → [crud-architecture.md](docs/platform/crud.md)
- Non-production reference surface (simple CRUD + master-detail orders) → [playground.md](docs/domains/playground/README.md)
- Dual-mode list/index (query JSON vs DataTables + RBAC actions) → [list-display-modes.md](docs/web/ui-patterns.md)
- Hierarchical, RBAC-filtered navigation → [navigation-menu.md](docs/domains/navigation/README.md)
- Role-targeted + personal activity notifications (events → roles/users) → [notifications.md](docs/domains/notification/README.md)
- Broadcast vs personal targeting (role vs user lifecycle reference) → [notifications-role-vs-user.md](docs/domains/notification/README.md)
- Adding or changing a notification type (agent checklist) → [notifications-implementation-guide.md](docs/domains/notification/README.md)
- Immutable mutation audit trail (Spatie activitylog) → [activity-log.md](docs/domains/activity-log/README.md)
- Auth sessions and password events in activity log → [auth-activity-assessment.md](docs/archive/auth-activity-assessment.md)
- ID format for users and Spatie models → [user-uuid-v7.md](docs/platform/identifiers.md)
- Blade admin frontend (API client), JWT-cookie → Bearer, `ApiClient`/`ConsumesApi`/`MonolithApi` → [web-frontend.md](docs/web/blade-admin.md)
- Order attachment direct-upload widget (manual queue, concurrency env) → [web-frontend.md — order direct-upload](docs/web/blade-admin.md#order-direct-upload-widget), [playground.md — web upload widget](docs/domains/playground/README.md#web-upload-widget-manual-queue)
- AJAX / DataTable error UX (auth expired, CSRF, proxy failures) → [web-frontend.md — AJAX error handling](docs/web/blade-admin.md#ajax-error-handling-contract)
- hCaptcha on public auth (API validation + Blade widget) → [jwt-authentication.md](docs/domains/identity/README.md#hcaptcha-defense-in-depth), [web-frontend.md](docs/web/blade-admin.md#hcaptcha-on-auth-forms)
- Multi-language (API, Blade, DB) → [localization.md](docs/platform/localization.md)
- Payments, channel checkout, invoices → [payment-gateways.md](docs/domains/payment-gateway/README.md)
- Database safety (tests vs MySQL, `ALLOW_DESTRUCTIVE_DB`) → [database-safety.md](docs/platform/database-safety.md)
- Long-lived worker safety for future Octane + Swoole deployment → [octane-readiness.md](docs/platform/octane.md)

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
npm run test:js                  # Vitest — JS unit tests (upload-queue scheduler)
npm run build                    # Vite production bundle (direct-upload.js, bell.js, app.css)
```

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
