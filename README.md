# Monolith

Laravel 13 API application with **JWT authentication**, **TOTP two-factor authentication (2FA)**, **Spatie RBAC**, **hierarchical user management (H-RBAC)**, and a **versioned API** under `/api/v1`. Business endpoints live on versioned routes; a small global surface exists for health checks and playground CRUD.

It also ships a server-rendered **Blade admin frontend** (AdminLTE 4 / Bootstrap 5) that is a **pure consumer of the versioned API**: every web controller forwards to `/api/v1/*`, so validation, CRUD, and RBAC run **only once — in the API** (no duplicated business logic). The browser authenticates with the **same stateless JWT, carried in an HttpOnly cookie**, which the web layer forwards to the API as a Bearer token. See **[Web Frontend (Blade + AdminLTE 4)](docs/web-frontend.md)**.

### Living documentation (required)

**This documentation is referenced on every task.** Use `README.md` and the guides under [`docs/`](docs/) as the primary reference before changing code, adding features, or completing work. **Always update the relevant documentation** (README and/or files in `docs/`) when your changes affect setup, conventions, the API, auth, the data schema, localization, or any behavior described here—do not let code and docs drift apart.

**All project documentation must be written in English** (README, `docs/`, and any new guides or sections you add).

**Localization (required on every task that touches copy):** Any change that adds or modifies user-facing text — Blade labels, API `message` strings, validation/policy errors, flash/AJAX copy, or database content shown to users — must include translations for **all** supported locales (`en`, `id`, `ja` today) in the **same task/PR**. Use `__('…')` with application-owned files under `lang/{locale}/` (never hardcode strings), or Spatie translatable fields for database content. See [Localization](docs/localization.md#task-rule-required) for the full checklist and conventions.

**Octane / Swoole readiness (required on every task):** All new code must follow the mandatory patterns in [Octane / Swoole Readiness](docs/octane-readiness.md) to survive long-lived worker processes. Never store request/user state on static properties or singletons. Never mutate facade/guard state without cleanup. Never call `env()` outside config files. Never use `config()->set()` at runtime. This project is not yet running Octane, but every line of code must be written as if it will tomorrow.

**Full-stack implementation (required on every task):** Every feature or change must be implemented end to end across **all relevant layers** in the **same task/PR** — do **not** ship work that only touches a single layer. At minimum, a typical task should cover:

| Layer | What to touch |
|-------|----------------|
| **API** | Versioned routes (`/api/v1/*`), controllers, form requests, policies, repositories, and API responses |
| **Web** | Web controllers that consume the API (`ConsumesApi` / `ApiClient`), routes in `routes/web.php`, and request forwarding |
| **Blade views** | Screens, forms, list tables, partials, and reusable components under `resources/views/{layout}/` (default layout: `adminlte4` via `WEB_LAYOUT` / `config/web.php`; see [web-frontend.md](docs/web-frontend.md#layout-namespace-and-configwebphp)) |
| **Translation** | All user-facing strings in `lang/{locale}/` for every supported locale (`en`, `id`, `ja`) |
| **Other (as applicable)** | Migrations, seeders, models, events/listeners, notifications, navigation menu entries, tests, and the relevant `docs/` guides |

A task that adds an API endpoint without the matching web route, Blade screen, and translations is **incomplete**. Likewise, a Blade-only or API-only change without its counterpart layers is not acceptable. See [Web frontend](docs/web-frontend.md), [CRUD architecture](docs/crud-architecture.md), and [Localization](docs/localization.md) for layer-specific conventions.

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
| Models | `User`, `Role`, `Permission`, `Order`, `OrderDetail`, `Attachment` use `HasUuids` |

When you add a table that belongs to a user or another UUID-backed model, use `foreignUuid` (not `foreignId`). Do not pass integer IDs in validation or route binding. Full rationale, migration examples, and pitfalls (e.g. do not use DB `gen_random_uuid()` for v7) → **[User primary keys (UUID v7)](docs/user-uuid-v7.md)**.

---

## Requirements

| Tool | Version |
|------|---------|
| PHP | ^8.3 (extensions: `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`) |
| Composer | 2.x |
| Node.js & npm | For Vite frontend assets (optional if you only use the API) |
| Database | SQLite (default in `.env.example`), or MySQL / PostgreSQL |

On **Laragon** (Windows), enable PHP 8.3+ and create a MySQL database if you do not use SQLite.

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
cp .env.example .env   # Windows: copy .env.example .env
php artisan key:generate
```

Copy `.env.example` and adjust at least:

| Variable | Notes |
|----------|--------|
| `APP_URL` | Base URL of the app (e.g. `http://monolith.test` on Laragon) |
| `DB_*` | Default is SQLite (`DB_CONNECTION=sqlite`). For MySQL, uncomment and set `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. |
| `JWT_*` | Set `JWT_SECRET` via step 4 below |
| `MAIL_*` | Required for email verification and password reset in real use (tests use `array` driver) |
| `BROADCAST_CONNECTION` / `PUSHER_*` | Optional locally (bell uses polling). In production, set Pusher or Socket-compatible credentials for realtime notifications — see [Activity notifications](docs/notifications.md#realtime-vs-polling) |

**SQLite:** ensure the database file exists:

```bash
touch database/database.sqlite   # Windows: type nul > database\database.sqlite
```

### 3. JWT signing secret

Required for this project (not covered by `composer setup`):

```bash
php artisan jwt:secret
```

This writes `JWT_SECRET` into `.env`. See [JWT Authentication & RBAC](docs/jwt-authentication.md#configuration).

### 4. Database migrations and seeders

```bash
php artisan migrate
php artisan db:seed
```

`DatabaseSeeder` runs `RoleSeeder` (roles, permissions, hierarchy levels) and creates a default admin user:

| Field | Value |
|-------|--------|
| Email | `admin@example.com` |
| Password | `123456789` |
| Role | `Admin` |

**Two-factor authentication (2FA):** Users can enable TOTP-based 2FA from their profile. Once enabled, login returns a `two_factor_token` (not a JWT); the client must call `POST /api/v1/auth/two-factor/login` with the token and a valid TOTP code to obtain the real JWT. See [JWT Authentication & RBAC](docs/jwt-authentication.md#two-factor-authentication-2fa) for full API and web flow.

To reset and reseed during development:

```bash
php artisan migrate:fresh --seed
```

### 5. Frontend assets

Required for the **notification bell** (Vite bundle with Laravel Echo + Pusher). AdminLTE/Bootstrap still load from CDN; only the bell uses Vite.

```bash
npm install
npm run build
```

`package.json` includes `laravel-echo` and `pusher-js` as runtime dependencies. Vite entries are declared in `vite.config.js` (`resources/js/app.js`, `resources/js/notifications/bell.js`).

For local development with hot reload:

```bash
npm run dev
```

### 6. Run the application

**API only:**

```bash
php artisan serve
```

**Full stack** (HTTP server, queue worker, log tail, Vite):

```bash
composer run dev
```

### One-shot setup (Composer script)

`composer.json` defines a `setup` script that installs PHP deps, copies `.env`, generates `APP_KEY`, runs migrations, and builds frontend assets. It does **not** run `jwt:secret` or seeders—run those after `composer run setup`:

```bash
composer run setup
php artisan jwt:secret
php artisan db:seed
```

---

## Verify the install

| Check | Command / URL |
|-------|----------------|
| Health check | `GET /api/ping` (global) or `GET /whatsup` |
| Login | `POST /api/v1/auth/login` with `admin@example.com` / `123456789` |
| Authenticated profile | `GET /api/v1/auth/me` with `Authorization: Bearer <token>` |
| Tests | `composer test` or `php artisan test` |
| Notification bell (local) | Sign in, open dashboard — badge polls every 60s (`APP_ENV=local` or missing Pusher credentials) |
| Notification bell (realtime) | Set `APP_ENV=production` + `PUSHER_*`, run `npm run build`, trigger a notification (e.g. create user) — badge updates without reload; see [notifications.md](docs/notifications.md#realtime-vs-polling) |
| Code style | `php artisan pint` — see [Laravel Pint](docs/laravel-pint.md) |

---

## Security

**Security Audit Report:** See [`SECURITY_AUDIT.md`](SECURITY_AUDIT.md) for the comprehensive OWASP Top 10:2025 security audit (20 findings, all critical and high priority vulnerabilities patched).

**Last Audit:** 2026-06-16 (Audit #1 - 19/20 findings resolved)

**Security Audit Procedure:** See [`docs/security-audit-procedure.md`](docs/security-audit-procedure.md) for repeatable audit methodology. Run security audits quarterly, before major deployments, or after significant feature changes.

**Production Security Checklist:**
- Set `APP_ENV=production` and `APP_DEBUG=false`
- Set `SESSION_SECURE_COOKIE=true` (enforce HTTPS-only cookies)
- Set `SESSION_ENCRYPT=true` (encrypt session data)
- Verify `JWT_SECRET` is generated and strong
- Playground routes are automatically disabled in production

---

## Project documentation

All guides live under [`docs/`](docs/). Read them in this order when onboarding:

### API surface and structure

| Document | Description |
|----------|-------------|
| [API routing](docs/api-routing.md) | Global (`/api/*`) vs versioned (`/api/v1/*`) routes, conventions, and where to add endpoints |
| [CRUD architecture & repository pattern](docs/crud-architecture.md) | Layered CRUD flow, `EloquentCrudRepository` vs dedicated repositories, `Route::apiResource` for simple resources, playground example, and the master-detail aggregate (Order + OrderDetail) with transactional writes |
| [Playground (reference surface)](docs/playground.md) | Non-production `/api/playground/*` demos: simple CRUD (Users) and the master-detail aggregate (Orders) with transactional writes, plus their web screens |
| [Approval matrix engine](docs/approval-matrix-engine.md) | Generic, code-free configurable approval workflow engine with conditional steps, parallel approver modes, SLA tracking, and delegation management - integrated with Order document type |
| [List display modes (query & DataTables)](docs/list-display-modes.md) | Dual-mode list/index responses, Yajra DataTables integration, RBAC action buttons for Bootstrap 4 / jQuery 3 |
| [Dynamic navigation menu (hierarchy + RBAC)](docs/navigation-menu.md) | Self-referential menu tree, per-viewer visibility, ordering, and the dedicated `MenuRepository` |
| [Activity notifications (per role + per user)](docs/notifications.md) | Generic event-driven notification engine: per-type audiences in **DB via seeder** (hybrid with code catalogue), per-user read state, navbar bell with polling or realtime websocket |
| [Activity notifications — implementation guide](docs/notifications-implementation-guide.md) | **AI-agent playbook:** step-by-step checklists to add types, wire dispatch, change audience, translations, tests, and DataTable reload (hybrid enum + dynamic audience) |
| [Activity log (audit trail)](docs/activity-log.md) | Spatie laravel-activitylog v5: immutable mutation audit (`activity_log`), automatic model logging, role-assignment events, API viewer + Blade screens |
| [Authentication activity assessment](docs/auth-activity-assessment.md) | Auth event coverage matrix: login/logout/2FA/password → activity log vs file log, gaps and security notes |
| [Web frontend (Blade + AdminLTE 4)](docs/web-frontend.md) | Server-rendered admin UI that **consumes the versioned API** (web = API client); JWT-in-HttpOnly-cookie auth forwarded as Bearer, reusable Blade components, user & menu management screens |

### Authentication and authorization

| Document | Description |
|----------|-------------|
| [JWT authentication & RBAC](docs/jwt-authentication.md) | JWT guard, register/login/refresh, Spatie roles & permissions, env config, local setup |
| [User management & H-RBAC](docs/user-management-hrbac.md) | `/api/v1/users` CRUD, role hierarchy (`roles.level`), policies, permissions matrix |
| [User primary keys (UUID v7)](docs/user-uuid-v7.md) | Why UUID v7 on `users`, migrations, and applying the same pattern to new models |

### Localization

| Document | Description |
|----------|-------------|
| [Localization (multi-language)](docs/localization.md) | Cookie/user locale resolution, `laravel-lang/common` file packs, Spatie translatable models, `lang/` layout, and validation strategy |

### Tooling

| Document | Description |
|----------|-------------|
| [Laravel Pint (Artisan wrapper)](docs/laravel-pint.md) | `php artisan pint` instead of calling the binary directly |

### Performance and deployment

| Document | Description |
|----------|-------------|
| [Octane / Swoole Readiness](docs/octane-readiness.md) | Rules and patterns for long-lived worker safety (mandatory before deploying Octane + Swoole) |

### Cross-links between docs

- Auth endpoints and middleware → [jwt-authentication.md](docs/jwt-authentication.md)
- User CRUD and hierarchy rules → [user-management-hrbac.md](docs/user-management-hrbac.md) (builds on JWT doc)
- Route placement for new features → [api-routing.md](docs/api-routing.md)
- Simple CRUD scaffolding (`Route::apiResource` + repository pattern) → [crud-architecture.md](docs/crud-architecture.md)
- Non-production reference surface (simple CRUD + master-detail orders) → [playground.md](docs/playground.md)
- Dual-mode list/index (query JSON vs DataTables + RBAC actions) → [list-display-modes.md](docs/list-display-modes.md)
- Hierarchical, RBAC-filtered navigation → [navigation-menu.md](docs/navigation-menu.md)
- Role-targeted + personal activity notifications (events → roles/users) → [notifications.md](docs/notifications.md)
- Adding or changing a notification type (agent checklist) → [notifications-implementation-guide.md](docs/notifications-implementation-guide.md)
- Immutable mutation audit trail (Spatie activitylog) → [activity-log.md](docs/activity-log.md)
- Auth sessions and password events in activity log → [auth-activity-assessment.md](docs/auth-activity-assessment.md)
- ID format for users and Spatie models → [user-uuid-v7.md](docs/user-uuid-v7.md)
- Blade admin frontend (API client), JWT-cookie → Bearer, `ApiClient`/`ConsumesApi` → [web-frontend.md](docs/web-frontend.md)
- Multi-language (API, Blade, DB) → [localization.md](docs/localization.md)
- Long-lived worker safety for future Octane + Swoole deployment → [octane-readiness.md](docs/octane-readiness.md)

---

## Common Artisan commands

```bash
php artisan migrate              # Apply migrations
php artisan db:seed              # Roles, permissions, sample admin
php artisan jwt:secret           # Generate JWT_SECRET (once per environment)
php artisan lang:update          # Refresh laravel-lang package files (also runs on composer update)
php artisan locale:add <locale>  # Onboard a new locale (managed files + config + app-owned scaffolding)
php artisan pint                 # Format PHP code
php artisan test                 # PHPUnit test suite
```

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
