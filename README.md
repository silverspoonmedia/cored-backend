# Monolith

Laravel 13 API application with **JWT authentication**, **Spatie RBAC**, **hierarchical user management (H-RBAC)**, and a **versioned API** under `/api/v1`. Business endpoints live on versioned routes; a small global surface exists for health checks and playground CRUD.

It also ships a server-rendered **Blade admin frontend** (AdminLTE 4 / Bootstrap 5) that is a **pure consumer of the versioned API**: every web controller forwards to `/api/v1/*`, so validation, CRUD, and RBAC run **only once — in the API** (no duplicated business logic). The browser authenticates with the **same stateless JWT, carried in an HttpOnly cookie**, which the web layer forwards to the API as a Bearer token. See **[Web Frontend (Blade + AdminLTE 4)](docs/web-frontend.md)**.

### Living documentation (required)

**This documentation is referenced on every task.** Use `README.md` and the guides under [`docs/`](docs/) as the primary reference before changing code, adding features, or completing work. **Always update the relevant documentation** (README and/or files in `docs/`) when your changes affect setup, conventions, the API, auth, the data schema, localization, or any behavior described here—do not let code and docs drift apart.

**All project documentation must be written in English** (README, `docs/`, and any new guides or sections you add).

**Localization (required on every task that touches copy):** Any change that adds or modifies user-facing text — Blade labels, API `message` strings, validation/policy errors, flash/AJAX copy, or database content shown to users — must include translations for **all** supported locales (`en`, `id`, `ja` today) in the **same task/PR**. Use `__('…')` with application-owned files under `lang/{locale}/` (never hardcode strings), or Spatie translatable fields for database content. See [Localization](docs/localization.md#task-rule-required) for the full checklist and conventions.

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
| Models | `User`, `Role`, `Permission` use `HasUuids` |

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

To reset and reseed during development:

```bash
php artisan migrate:fresh --seed
```

### 5. Frontend assets (optional)

```bash
npm install
npm run build
```

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
| Code style | `php artisan pint` — see [Laravel Pint](docs/laravel-pint.md) |

---

## Project documentation

All guides live under [`docs/`](docs/). Read them in this order when onboarding:

### API surface and structure

| Document | Description |
|----------|-------------|
| [API routing](docs/api-routing.md) | Global (`/api/*`) vs versioned (`/api/v1/*`) routes, conventions, and where to add endpoints |
| [CRUD architecture & repository pattern](docs/crud-architecture.md) | Layered CRUD flow, `EloquentCrudRepository` vs dedicated repositories, `Route::apiResource` for simple resources, playground example |
| [List display modes (query & DataTables)](docs/list-display-modes.md) | Dual-mode list/index responses, Yajra DataTables integration, RBAC action buttons for Bootstrap 4 / jQuery 3 |
| [Dynamic navigation menu (hierarchy + RBAC)](docs/navigation-menu.md) | Self-referential menu tree, per-viewer visibility, ordering, and the dedicated `MenuRepository` |
| [Activity notifications (per role + per user)](docs/notifications.md) | Generic event-driven notification engine: broadcast to whole roles and/or target specific users, per-user read state, navbar bell |
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

### Cross-links between docs

- Auth endpoints and middleware → [jwt-authentication.md](docs/jwt-authentication.md)
- User CRUD and hierarchy rules → [user-management-hrbac.md](docs/user-management-hrbac.md) (builds on JWT doc)
- Route placement for new features → [api-routing.md](docs/api-routing.md)
- Simple CRUD scaffolding (`Route::apiResource` + repository pattern) → [crud-architecture.md](docs/crud-architecture.md)
- Dual-mode list/index (query JSON vs DataTables + RBAC actions) → [list-display-modes.md](docs/list-display-modes.md)
- Hierarchical, RBAC-filtered navigation → [navigation-menu.md](docs/navigation-menu.md)
- Role-targeted + personal activity notifications (events → roles/users) → [notifications.md](docs/notifications.md)
- ID format for users and Spatie models → [user-uuid-v7.md](docs/user-uuid-v7.md)
- Blade admin frontend (API client), JWT-cookie → Bearer, `ApiClient`/`ConsumesApi` → [web-frontend.md](docs/web-frontend.md)
- Multi-language (API, Blade, DB) → [localization.md](docs/localization.md)

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
