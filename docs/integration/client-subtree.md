# Client project integration via git subtree

How a downstream client project consumes this repository as its `core/` subtree, and how to pull or contribute changes back. Human how-to only — the two scripts it relies on live at the repo root: [`update-core.sh`](../../update-core.sh), [`push-core.sh`](../../push-core.sh).

## 1. Architecture overview

| Repository | Role | Contains |
|------------|------|----------|
| `monolith` | **Core** — standalone Laravel 13 app | Shared backend: domain logic, config, routes, migrations, seeders, API |
| `client-project` | **Client-specific** — extends core | Client-only domain code, routes, migrations, config values |

The client's browser UI is not part of the subtree: the sibling `monolith-vue-workers` SPA is deployed separately.

## 2. Folder structure

| Path | Role |
|------|------|
| `core/` | Read-only upstream. Do not edit here unless you intend to contribute back with `push-core.sh` |
| `app/` | Client-only code. To override a core class, create the same namespace path here — PSR-4 resolves `app/` before `core/app/` |
| `config/` | Client config; core's `core/config/` is the reference default and is never edited |
| `database/migrations/` | Client migrations. Core migrations stay in `core/database/migrations/` |
| `routes/` | Client routes. Core routes load from `core/routes/` via `bootstrap/app.php` |
| `bootstrap/app.php` | The integration file: loads core + client routes, middleware and exception handlers |

## 3. Integration strategy

**PSR-4 overlay.** Autoload both roots under one namespace:

```json
"autoload": { "psr-4": { "App\\": ["app/", "core/app/"] } }
```

Core classes are available with no import changes; an override is a file at the same relative path in `app/`. Core is never modified for a client.

**Config merging.** `git subtree pull` updates `core/config/` only — it never touches the client's `config/`. New core keys must be merged into the client's copies by hand after each pull.

**Path packages take part of the load.** Extracted core domains install as `silverspoon/*` path packages, namespace `App\Domain\{Domain}\*` unchanged, so the overlay still wins — [core-packages.md](core-packages.md). The subtree model is unchanged; it no longer carries all of the core code itself.

**Routes and middleware** are registered in the client's `bootstrap/app.php`: load `core/routes/*.php`, then the client's own route files, and register core middleware groups before client additions.

**When to override vs contribute:** bug in core → fix in `core/`, test, `push-core.sh`, pull into every client · feature useful to all clients → develop in `core/`, push, pull · client-specific behavior (code, config, route) → implement in the client, outside `core/`.

## 4. Setup (new client project)

```bash
composer create-project laravel/laravel client-project
cd client-project
git init
git remote add monolith https://github.com/silverspoonmedia/monolith.git
git subtree add --prefix=core/ monolith main --squash
```

Then: copy every `require` / `require-dev` entry from `core/composer.json` into the client manifest, add the overlay autoload paths, `composer install`; write `bootstrap/app.php` from `core/bootstrap/app.php`; copy the core config files you intend to customize into `config/`; set `.env`; then

```bash
php artisan migrate
php artisan db:seed
php artisan route:list   # must show core + client routes
# then copy update-core.sh + push-core.sh from the core root into the client root
```

## 5. Daily workflow

```bash
./update-core.sh                 # pull core updates
git fetch monolith               # or manually
git subtree pull --prefix=core/ monolith main --squash
git diff --stat core/            # what changed
diff -rq core/config/ config/    # new core config keys to merge by hand
composer install                 # if core dependencies changed
php artisan migrate              # core migrations
./push-core.sh                   # contribute core changes upstream (intentional only)
```

`push-core.sh` refuses a dirty tree and pushes **every** commit that touched `core/` — treat it as an upstream release, not a sync.

## 6. Quick reference

| Task | Command |
|------|---------|
| Pull core | `git subtree pull --prefix=core/ monolith main --squash` |
| Push core | `git subtree push --prefix=core/ monolith main` |
| Check drift | `git fetch monolith && git diff --stat core/` |

Automated checks: [`ClientSubtreeIntegrationDocTest`](../../tests/Unit/Documentation/ClientSubtreeIntegrationDocTest.php), [`CoreScriptTest`](../../tests/Unit/Documentation/CoreScriptTest.php).
