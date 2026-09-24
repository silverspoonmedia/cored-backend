# Client Project Integration via Git Subtree

> **Target audience:** Freelance developers using `monolith` as a Laravel 13 boilerplate/core.  
> **Goal:** Keep `monolith` as a standalone source of truth while each client project pulls it as a subtree and extends it with client-specific code.

---

## 1. Architecture Overview

### Repository roles

| Repository | Role | Contains |
|---|---|---|
| `monolith` | **Core** — standalone Laravel 13 app | All shared components (controllers, middleware, models, config, routes, views, helpers, etc.) |
| `client-project` | **Client-specific** — extends Core | Business logic, custom routes, views, migrations that belong to one client only |

### How they connect

```
monolith (Core repo)
    │
    │  git subtree pull / push
    ▼
client-project/
├── core/          ← monolith pulled as Git Subtree (read-only in daily workflow)
├── app/           ← client-specific business logic
├── config/        ← client-specific or override config
├── routes/        ← client-specific routes
├── public/        ← entry point (index.php)
├── composer.json  ← project dependencies
├── artisan        ← CLI entry point
└── ...
```

**Key principle:** `core/` is treated as upstream. Daily development happens OUTSIDE `core/`. When `monolith` is updated (bug fixes, new features), pull those changes into `core/` via `git subtree pull`.

---

## 2. Client Project Folder Structure

```
client-project/
├── core/                        ← Git Subtree: entire monolith repository
│   ├── app/                     ← Core controllers, models, middleware, traits, etc.
│   ├── bootstrap/               ← Core bootstrap files (reference only)
│   ├── config/                  ← Core configuration files
│   ├── database/                ← Core migrations, factories, seeders
│   ├── docs/                    ← Core documentation
│   ├── lang/                    ← Core translation files
│   ├── resources/               ← Core views, assets
│   ├── routes/                  ← Core route definitions
│   ├── tests/                   ← Core test suite
│   ├── composer.json            ← Core dependencies (reference)
│   └── ...
│
├── app/                         ← Client-specific business logic
│   ├── Http/
│   │   ├── Controllers/         ← Client-specific controllers
│   │   └── Middleware/          ← Client-specific middleware
│   ├── Models/                  ← Client-specific Eloquent models
│   ├── Services/                ← Client-specific service classes
│   ├── Repositories/            ← Client-specific repositories
│   └── Support/                 ← Client-specific helpers, traits
│
├── bootstrap/
│   ├── app.php                  ← Client Application bootstrap (includes core)
│   └── providers.php            ← Client service providers
│
├── config/                      ← Client configuration (overrides/extends core config)
│
├── database/
│   ├── migrations/              ← Client-specific migrations
│   ├── factories/               ← Client-specific model factories
│   └── seeders/                 ← Client-specific database seeders
│
├── lang/                        ← Client-specific translations
│   ├── en/
│   ├── id/
│   └── ja/
│
├── public/
│   ├── index.php                ← HTTP entry point
│   └── .htaccess
│
├── resources/
│   ├── views/                   ← Client-specific Blade views (extend core layouts)
│   ├── js/                      ← Client-specific JavaScript
│   └── css/                     ← Client-specific CSS
│
├── routes/
│   ├── web.php                  ← Client-specific web routes
│   └── api.php                  ← Client-specific API routes
│
├── storage/                     ← Laravel storage (logs, cache, uploads)
│
├── tests/                       ← Client-specific tests
│
├── .env                         ← Environment configuration (git-ignored)
├── .env.example                 ← Environment template
├── .gitignore
├── artisan                      ← Laravel CLI (symlink or copy from core)
├── composer.json                ← Project dependencies (core + client-specific)
├── package.json                 ← Frontend dependencies
├── update-core.sh               ← Script to pull latest core updates
├── push-core.sh                 ← Script to push core changes back to monolith
└── README.md                    ← Project-specific documentation
```

### File/Directory Roles

| Path | Role |
|---|---|
| `core/` | **Read-only upstream.** Pulled from `monolith`. Do NOT edit files here directly (unless contributing back to monolith, then use `push-core.sh`). |
| `app/` | **Client-only code.** Controllers, models, services specific to this client. If you need to override a core class, place a file with the same namespace path here — PSR-4 overlay resolves `app/` before `core/app/`. |
| `config/` | **Client configuration.** Contains full copies of core config files that you customize. Core configs in `core/config/` serve as reference defaults. |
| `database/migrations/` | **Client migrations** for client-specific tables. Core migrations are in `core/database/migrations/`. |
| `resources/views/` | **Client Blade views.** Extend core layouts from `core/resources/views/`. |
| `routes/` | **Client routes.** Core routes are loaded from `core/routes/` via bootstrap. |
| `bootstrap/app.php` | **Client bootstrap.** The single most important integration file — loads both core and client configurations. |
| `composer.json` | **Master dependency manifest.** Lists ALL dependencies (core + client). Autoload maps both `app/` and `core/app/` under the same namespace. |
| `update-core.sh` | Pull latest `monolith` changes into `core/`. |
| `push-core.sh` | Push changes made inside `core/` back to `monolith` (for contributing fixes upstream). |

---

## 3. Integration Strategy: Coexistence Without Conflicts

### 3.1 PSR-4 Overlay (The Secret Sauce)

The client `composer.json` maps the `App\` namespace to BOTH directories:

```json
"autoload": {
    "psr-4": {
        "App\\": ["app/", "core/app/"],
        "Database\\Factories\\": ["database/factories/", "core/database/factories/"],
        "Database\\Seeders\\": ["database/seeders/", "core/database/seeders/"]
    }
}
```

**How it works:** Composer's PSR-4 autoloader searches paths in order. If a class exists in `app/`, it wins. If not, it falls through to `core/app/`. This gives you:

- **Automatic inheritance:** All core classes (`App\Http\Controllers\...`, `App\Models\...`, etc.) are available without any import changes.
- **Selective override:** To override a core class, create a file with the SAME path and class name in `app/`. Example: to customize `Core\App\Models\User`, create `app/Models/User.php` and extend or replace the logic.
- **No core modifications needed:** `monolith` repository stays untouched.

### 3.2 Config Merging

Core configs live in `core/config/`. The client project maintains its own set in `config/`.

**Strategy:** On initial setup, copy all config files from `core/config/` into `config/`. The client owns these copies and can customize any value. When `monolith` is updated:

- New config keys in core need to be merged into client's `config/` files manually.
- `git subtree pull` only updates `core/config/` — it does NOT touch client's `config/`.

**Recommendation:** After a subtree pull, diff `core/config/` against `config/` to spot new keys:

```bash
diff -r config/ core/config/
```

### 3.3 Route Loading (bootstrap/app.php)

The client's `bootstrap/app.php` loads BOTH core routes and client routes:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
// Import core middleware classes
use App\Http\Middleware\AuthenticateWithJwtCookie;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
// ... other imports from core

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Client API routes (global)
        api: __DIR__.'/../routes/api.php',

        // Core versioned API routes
        then: function (): void {
            // Core v1 API
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('core/routes/api/v1.php'));

            // Client-specific v1 API (if needed)
            if (file_exists(base_path('routes/api/v1.php'))) {
                Route::middleware('api')
                    ->prefix('api/v1')
                    ->name('api.v1.')
                    ->group(base_path('routes/api/v1.php'));
            }
        },

        // Core web routes first, then client web routes
        web: [
            __DIR__.'/../core/routes/web.php',
            __DIR__.'/../routes/web.php',
        ],

        commands: [
            __DIR__.'/../core/routes/console.php',
            __DIR__.'/../routes/console.php',
        ],

        channels: __DIR__.'/../core/routes/channels.php',
        health: '/whatsup',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // --- Core middleware configuration ---
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->web(append: [
            AuthenticateWithJwtCookie::class,
            SetLocale::class,
            SecurityHeaders::class,
        ]);

        $middleware->api(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'jwt.optional' => \App\Http\Middleware\OptionalJwtAuthenticate::class,
            'web.auth' => \App\Http\Middleware\EnsureWebAuthenticated::class,
            'web.guest' => \App\Http\Middleware\RedirectIfWebAuthenticated::class,
        ]);

        // --- Client-specific middleware ---
        // Add any client-only middleware here
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // --- Core exception handlers ---
        // Copy core exception handling logic here (see core/bootstrap/app.php)
        // Or better: include a shared exception config file

        $exceptions->shouldRenderJsonWhen(
            fn (\Illuminate\Http\Request $request) => $request->is('api/*'),
        );

        // ... copy core's exception handlers here (defined in core/bootstrap/app.php)

        // --- Client-specific exception handlers ---
        // Add any client-only exception handling here
    })->create();
```

**Important:** The client's `bootstrap/app.php` is the MASTER file. It replicates the structure from `core/bootstrap/app.php` with additions for client-specific needs. The core's `bootstrap/app.php` inside `core/` serves as a **reference** only — it is NOT executed by the client project.

**Why not `require` the core app.php?** The core `bootstrap/app.php` uses `dirname(__DIR__)` for paths, which would resolve incorrectly when called from the client. The cleanest approach is to maintain the client's own bootstrap file that mirrors core's structure. When core's bootstrap changes, manually update the client's.

### 3.4 composer.json Strategy

The client's `composer.json` must include:

1. **All core dependencies** (copy from `core/composer.json`'s `require` and `require-dev`)
2. **Client-specific dependencies** (any additional packages needed by this client)
3. **Overlay autoload mapping** (PSR-4 with both `app/` and `core/app/`)
4. **Core scripts** (copy relevant scripts from core)

**Initial setup pattern:**

```json
{
    "name": "client/client-project",
    "type": "project",
    "require": {
        "php": "^8.4",
        "laravel/framework": "^13.8",
        "laravel/tinker": "^3.0",
        "php-open-source-saver/jwt-auth": "^2.9",
        "spatie/laravel-permission": "^8.0",
        "...": "..."
    },
    "autoload": {
        "psr-4": {
            "App\\": ["app/", "core/app/"],
            "Database\\Factories\\": ["database/factories/", "core/database/factories/"],
            "Database\\Seeders\\": ["database/seeders/", "core/database/seeders/"]
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": ["tests/", "core/tests/"]
        }
    }
}
```

### 3.5 artisan and public/index.php

The `artisan` file and `public/index.php` are **entry points** — they must live at the project root. The client project owns these.

- **`artisan`:** Standard Laravel artisan file. No changes needed — it boots the Application from `bootstrap/app.php`.
- **`public/index.php`:** Standard Laravel front controller. No changes needed.

Do NOT use the copies inside `core/` — those are reference files only.

### 3.6 .env and Environment

The client project owns `.env` and `.env.example`. The core's `.env.example` inside `core/` is a reference.

The client's `.env.example` should include ALL variables from core + any client-specific additions.

### 3.7 Override Pattern Examples

**Overriding a core controller:**

```php
// File: app/Http/Controllers/Web/Auth/LoginController.php
// This OVERRIDES core's App\Http\Controllers\Web\Auth\LoginController

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Controller;

class LoginController extends Controller
{
    // Custom login logic specific to this client
    public function create()
    {
        // Client-specific login page
    }
}
```

When Composer resolves `App\Http\Controllers\Web\Auth\LoginController`, it finds the class in `app/` first and uses that. Core's version in `core/app/` is ignored.

**Overriding a core model:**

```php
// File: app/Models/User.php
// This OVERRIDES core's App\Models\User

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
// Extend core's base User from core/app/Models/User.php
// Since core's User is at core/app/Models/User.php, 
// you can't extend it because the namespace is the same.
// Instead, COPY the core User and modify it, or use a trait-based approach.

// RECOMMENDED: If you need to modify core's User model, either:
// 1. Contribute the change back to monolith (if it benefits all clients)
// 2. Use a trait that both core User and client User share
// 3. Copy the full User model to app/ and maintain it separately
```

**Limitation:** You cannot extend a core class from `core/app/` because both share the `App\` namespace. Two approaches:
1. **Copy and modify** the entire class in `app/` (full override)
2. **Contribute upstream** changes to `monolith` when they benefit all clients
3. **Use traits** — core defines traits that the client composes differently

### 3.8 When to Override vs. When to Contribute

| Scenario | Action |
|---|---|
| Bug fix in core code | Fix in `core/`, test, push to `monolith` via `push-core.sh`, then pull to all clients |
| Feature that benefits all clients | Develop in `core/`, push to `monolith`, pull to all clients |
| Client-specific behavior | Implement in `app/` (outside `core/`) |
| Config customization | Modify client's `config/` files |
| Route customization | Add routes to client's `routes/` files |

---

## 4. Setup Commands: New Client Project From Scratch

### 4.1 Create Laravel project base

```bash
# Create a new Laravel 13 project
composer create-project laravel/laravel client-project ^13.8
cd client-project
```

### 4.2 Initialize Git and add monolith as remote

```bash
# Initialize git repository
git init
git add -A
git commit -m "Initial Laravel scaffold"

# Add monolith as a remote
git remote add monolith https://github.com/silverspoonmedia/monolith.git
```

### 4.3 Pull monolith as subtree into core/

```bash
# Add the monolith subtree — use --squash to keep history clean
git subtree add --prefix=core/ monolith main --squash

# Verify the subtree was pulled
ls core/
git log --oneline -3
```

**Why `--squash`?** Without squash, all of monolith's commit history gets merged into the client's git log — thousands of irrelevant commits. `--squash` condenses the entire monolith history into a single merge commit in the client project. Clean and simple.

### 4.4 Configure composer.json

```bash
# Read core dependencies to inform your composer.json
cat core/composer.json

# Manually edit composer.json:
# 1. Copy all require/require-dev dependencies from core/composer.json
# 2. Add overlay autoload paths (see Section 3.1)
# 3. Add any client-specific packages

# After editing, install dependencies
composer install
```

### 4.5 Set up bootstrap/app.php

```bash
# The client needs its own bootstrap/app.php that:
# 1. Loads core routes from core/routes/
# 2. Loads client routes from routes/
# 3. Configures core middleware + client middleware
# 4. Sets up core exception handlers + client exception handlers

# Copy the structure from core/bootstrap/app.php as a starting point,
# then add your client-specific routing groups and middleware.

# See Section 3.3 for the full template.
```

### 4.6 Configure config files

```bash
# On initial setup, copy all core configs to client's config/
cp -r core/config/* config/

# Now customize any values in config/ for this client.
# Core's core/config/ serves as reference — do not edit it directly.
```

### 4.7 Environment setup

```bash
# Copy and customize environment
cp core/.env.example .env            # Windows: copy core\.env.example .env
php artisan key:generate
php artisan jwt:secret

# Edit .env for your database, mail, etc.
```

### 4.8 Run migrations and seed

```bash
# Run core migrations + client migrations
php artisan migrate

# Seed core data (roles, permissions, admin user)
php artisan db:seed
```

### 4.9 Verify the setup

```bash
# Check that Artisan works
php artisan --version

# List all routes (should include core + client routes)
php artisan route:list

# Check health endpoint
php artisan serve
# Visit http://localhost:8000/whatsup

# Run tests (core + client)
php artisan test
```

---

## 5. Daily Workflow Commands

### 5.1 Pull latest updates from monolith

```bash
# Fetch latest from monolith remote
git fetch monolith main

# Pull updates into core/ subtree
git subtree pull --prefix=core/ monolith main --squash

# Check what changed in core/
git log --oneline core/ -5

# After pulling, check for new config keys
diff -rq config/ core/config/ | grep "differ" || echo "No config differences"

# If configs differ, manually review and merge changes into config/
# Check for new dependencies in core/composer.json vs your composer.json
# Run composer install if deps changed
# Run any new migrations
php artisan migrate
```

### 5.2 Push changes from core/ back to monolith

```bash
# Ensure you're on a clean working tree
git status

# Push changes made inside core/ back to monolith
git subtree push --prefix=core/ monolith main

# CAUTION: This pushes ALL commits that touched core/ to monolith.
# Only do this if you intentionally modified files inside core/ for upstream contribution.
```

### 5.3 Check if core/ is up-to-date with monolith

```bash
# Fetch latest from monolith
git fetch monolith main

# Compare the subtree split with upstream
git diff monolith/main -- core/

# Or: check the last subtree merge commit
git log --oneline --grep="git-subtree-split" -1

# Show what monolith has that we don't
git log HEAD..monolith/main --oneline
```

### 5.4 Full sync check (detailed)

```bash
# Fetch the latest
git fetch monolith main

# Show commits in monolith that weren't pulled yet
echo "=== Commits in monolith not in your core/ ==="
git log HEAD..monolith/main --oneline

# Show files that differ between core/ and monolith's main
echo "=== Files different from upstream ==="
git diff --stat monolith/main -- core/

# If output is empty, core/ is up-to-date.
```

---

## 6. Script: update-core.sh

Create this file at the project root. Make it executable with `chmod +x update-core.sh`.

```bash
#!/usr/bin/env bash
set -euo pipefail

# ============================================================
# update-core.sh — Pull latest monolith updates into core/
#
# Usage: ./update-core.sh
#
# Configurable remote URL:
#   Update MONOLITH_REMOTE and MONOLITH_BRANCH below.
#   The script auto-registers the remote if missing.
# ============================================================

MONOLITH_URL="${MONOLITH_URL:-https://github.com/silverspoonmedia/monolith.git}"
MONOLITH_REMOTE="${MONOLITH_REMOTE:-monolith}"
MONOLITH_BRANCH="${MONOLITH_BRANCH:-main}"
CORE_PREFIX="${CORE_PREFIX:-core}"

# ── 1. Ensure monolith remote exists ────────────────────────
echo "Checking remote '${MONOLITH_REMOTE}'..."

if ! git remote get-url "${MONOLITH_REMOTE}" &>/dev/null; then
    echo "Remote '${MONOLITH_REMOTE}' not found. Adding ${MONOLITH_URL} ..."
    git remote add "${MONOLITH_REMOTE}" "${MONOLITH_URL}"
    echo "Remote '${MONOLITH_REMOTE}' added."
else
    CURRENT_URL=$(git remote get-url "${MONOLITH_REMOTE}")
    echo "Remote '${MONOLITH_REMOTE}' exists → ${CURRENT_URL}"
fi

# ── 2. Fetch latest from monolith ───────────────────────────
echo ""
echo "Fetching ${MONOLITH_REMOTE}/${MONOLITH_BRANCH}..."
git fetch "${MONOLITH_REMOTE}" "${MONOLITH_BRANCH}"

# ── 3. Check if there's anything new ────────────────────────
echo ""
echo "Checking for new commits..."

BEHIND_COUNT=$(git rev-list --count HEAD.."${MONOLITH_REMOTE}/${MONOLITH_BRANCH}" 2>/dev/null || echo "0")

if [ "${BEHIND_COUNT}" -eq 0 ]; then
    echo "'${CORE_PREFIX}/' is already up-to-date with ${MONOLITH_REMOTE}/${MONOLITH_BRANCH}."
    exit 0
fi

echo "${BEHIND_COUNT} new commit(s) available from ${MONOLITH_REMOTE}/${MONOLITH_BRANCH}."

# ── 4. Show what's coming ───────────────────────────────────
echo ""
echo "Incoming commits:"
git log --oneline HEAD.."${MONOLITH_REMOTE}/${MONOLITH_BRANCH}"

# ── 5. Pull subtree with squash ─────────────────────────────
echo ""
echo "Pulling ${MONOLITH_REMOTE}/${MONOLITH_BRANCH} into '${CORE_PREFIX}/' (--squash)..."
git subtree pull --prefix="${CORE_PREFIX}/" "${MONOLITH_REMOTE}" "${MONOLITH_BRANCH}" --squash

# ── 6. Post-pull checks ────────────────────────────────────
echo ""
echo "Subtree pull complete."

echo ""
echo "Files changed:"
git diff --stat HEAD~1 -- "${CORE_PREFIX}/" 2>/dev/null || echo "(first pull or no diff available)"

echo ""
echo "Checking for config differences between '${CORE_PREFIX}/config/' and 'config/'..."
if diff -rq config/ "${CORE_PREFIX}/config/" 2>/dev/null | grep -q "differ"; then
    echo "  Config files differ! Review and manually merge changes:"
    diff -rq config/ "${CORE_PREFIX}/config/" | grep "differ"
else
    echo "No config differences detected."
fi

echo ""
echo " Checking for new Composer dependencies..."
echo "   Compare '${CORE_PREFIX}/composer.json' with your 'composer.json'."
echo "   Run 'composer install' if dependencies changed."

echo ""
echo " Checking for new migrations..."
NEW_MIGRATIONS=$(find "${CORE_PREFIX}/database/migrations/" -name "*.php" -newer vendor/autoload.php 2>/dev/null | wc -l || echo "0")
if [ "${NEW_MIGRATIONS}" -gt 0 ]; then
    echo "  New migrations detected. Run: php artisan migrate"
else
    echo "No new migrations detected."
fi

echo ""
echo " Done. '${CORE_PREFIX}/' is now up-to-date."
```

---

## 7. Script: push-core.sh

Create this file at the project root. Make it executable with `chmod +x push-core.sh`.

```bash
#!/usr/bin/env bash
set -euo pipefail

# ============================================================
# push-core.sh — Push changes from core/ back to monolith
#
# Usage: ./push-core.sh
#
# WARNING: This pushes ALL commits that touched core/ back to
# the monolith repository. Only use when you intentionally
# modified files inside core/ for upstream contribution.
# ============================================================

MONOLITH_URL="${MONOLITH_URL:-https://github.com/silverspoonmedia/monolith.git}"
MONOLITH_REMOTE="${MONOLITH_REMOTE:-monolith}"
MONOLITH_BRANCH="${MONOLITH_BRANCH:-main}"
CORE_PREFIX="${CORE_PREFIX:-core}"

# ── 1. Ensure monolith remote exists ────────────────────────
echo "Checking remote '${MONOLITH_REMOTE}'..."

if ! git remote get-url "${MONOLITH_REMOTE}" &>/dev/null; then
    echo "Remote '${MONOLITH_REMOTE}' not found. Adding ${MONOLITH_URL} ..."
    git remote add "${MONOLITH_REMOTE}" "${MONOLITH_URL}"
    echo "Remote '${MONOLITH_REMOTE}' added."
else
    CURRENT_URL=$(git remote get-url "${MONOLITH_REMOTE}")
    echo "Remote '${MONOLITH_REMOTE}' exists → ${CURRENT_URL}"
fi

# ── 2. Check for uncommitted changes ────────────────────────
echo ""
echo "Checking working tree..."

if ! git diff-index --quiet HEAD --; then
    echo " Working tree has uncommitted changes. Commit or stash them first."
    git status --short
    exit 1
fi

# ── 3. Show what will be pushed ─────────────────────────────
echo ""
echo "Commits that touched '${CORE_PREFIX}/' (not yet in ${MONOLITH_REMOTE}/${MONOLITH_BRANCH}):"

# Get the last subtree split commit
LAST_SPLIT=$(git log --oneline --grep="git-subtree-dir: ${CORE_PREFIX}" -1 --format="%H" 2>/dev/null || echo "")

if [ -n "${LAST_SPLIT}" ]; then
    COMMITS_TO_PUSH=$(git log --oneline "${LAST_SPLIT}..HEAD" -- "${CORE_PREFIX}/" 2>/dev/null || echo "")
else
    COMMITS_TO_PUSH=$(git log --oneline -- "${CORE_PREFIX}/" 2>/dev/null || echo "")
fi

if [ -z "${COMMITS_TO_PUSH}" ]; then
    echo "   (no local commits to push)"
    echo ""
    echo "Nothing to push. '${CORE_PREFIX}/' has no changes to send upstream."
    exit 0
fi

echo "${COMMITS_TO_PUSH}"

# ── 4. Show files that will be pushed ───────────────────────
echo ""
echo " Changed files in '${CORE_PREFIX}/':"
if [ -n "${LAST_SPLIT}" ]; then
    git diff --stat "${LAST_SPLIT}..HEAD" -- "${CORE_PREFIX}/" 2>/dev/null || echo "(no diff)"
else
    git diff --stat HEAD~1..HEAD -- "${CORE_PREFIX}/" 2>/dev/null || echo "(no diff)"
fi

# ── 5. Confirmation ─────────────────────────────────────────
echo ""
echo "  WARNING: This will push changes from '${CORE_PREFIX}/' to"
echo "   ${MONOLITH_REMOTE} (${MONOLITH_URL}) on branch '${MONOLITH_BRANCH}'."
echo ""
read -r -p "Continue? [y/N] " CONFIRM

if [ "${CONFIRM}" != "y" ] && [ "${CONFIRM}" != "Y" ]; then
    echo " Push cancelled."
    exit 0
fi

# ── 6. Push subtree ─────────────────────────────────────────
echo ""
echo "Pushing '${CORE_PREFIX}/' to ${MONOLITH_REMOTE} ${MONOLITH_BRANCH}..."
git subtree push --prefix="${CORE_PREFIX}/" "${MONOLITH_REMOTE}" "${MONOLITH_BRANCH}"

echo ""
echo "Push complete."
```

---

## 8. Quick Reference Card

```bash
# SETUP (new client project)
composer create-project laravel/laravel client-project ^13.8
cd client-project
git init && git add -A && git commit -m "Init"
git remote add monolith https://github.com/silverspoonmedia/monolith.git
git subtree add --prefix=core/ monolith main --squash
# ... then configure bootstrap/app.php, composer.json, config/, .env

# DAILY WORKFLOW
./update-core.sh              # Pull latest core updates
php artisan migrate           # Run any new migrations
php artisan test              # Verify nothing broke

# CONTRIBUTE BACK TO CORE
./push-core.sh                # Push changes from core/ to monolith

# CHECK SYNC STATUS
git fetch monolith main
git log HEAD..monolith/main --oneline   # What's new upstream?
git diff monolith/main -- core/         # What files differ?
```