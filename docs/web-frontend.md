# Web Frontend (Blade + AdminLTE 4)

This application is **API-first**, but it also ships a server-rendered **Blade** admin frontend built on **AdminLTE 4 (Bootstrap 5 + Bootstrap Icons)**. The frontend does **not** introduce a second authentication system or duplicate business logic: it reuses the exact same **Services**, **Form Requests**, **Policies**, and **Spatie permissions** as the versioned API. Only the response shape differs — Blade views and redirects instead of JSON.

> The backend remains the source of truth. See [JWT Authentication & RBAC](./jwt-authentication.md), [User Management & H-RBAC](./user-management-hrbac.md), [Dynamic Navigation Menu](./navigation-menu.md), and [CRUD Architecture](./crud-architecture.md).

---

## Authentication model: stateless JWT in an HttpOnly cookie

The API is stateless JWT and intentionally has **no session login route**. To keep the browser on the same auth stack (and avoid a parallel session guard that would drift from the API), the web layer uses a **JWT-in-HttpOnly-cookie** bridge:

```
Browser form POST  ──► Web controller ──► AuthService::login()  (issues JWT, same as API)
                                          │
                                          ▼
                          Set HttpOnly cookie `auth_token` = JWT
                                          │
Every later web request ──► AuthenticateWithJwtCookie ──► binds the JWT to the `api` guard
                                          │
                          auth()->user(), `permission:`/`role:` middleware,
                          and UserPolicy all work unchanged on web routes
```

| Piece | File | Role |
|-------|------|------|
| Cookie factory | `app/Support/Auth/AuthCookie.php` | Builds/forgets the HttpOnly `auth_token` cookie (lifetime mirrors the token's `expires_in`) |
| Guard bridge | `app/Http/Middleware/AuthenticateWithJwtCookie.php` | Appended to the `web` middleware group; reads the cookie and resolves the `api` guard. Never rejects — a bad/expired token = guest |
| Auth gate | `app/Http/Middleware/EnsureWebAuthenticated.php` (alias `web.auth`) | Redirects guests to `login` (stores intended URL); the web analogue of `auth:api` |
| Guest gate | `app/Http/Middleware/RedirectIfWebAuthenticated.php` (alias `web.guest`) | Bounces authenticated users away from login/register/reset |

Because the default guard is already `api` (`config/auth.php`), once the cookie middleware resolves the user, **Spatie `permission:`/`role:` middleware and `UserPolicy` require zero changes** to run on web routes.

**Logout** calls `AuthService::logout()` (blacklists the JWT, same as the API) and forgets the cookie.

The server keeps **no session-based identity**; the session is used only for CSRF, flash messages, and the "intended URL". Identity is always the stateless JWT.

---

## DRY: how the web layer reuses the backend

| Concern | Reused from the API | Web-only glue |
|---------|---------------------|---------------|
| Validation | `App\Http\Requests\Auth\*`, `…\User\*`, `…\Navigation\*` (the *same* Form Request classes) | On a non-JSON request these auto-redirect back with `$errors` instead of returning 422 JSON |
| Business logic | `AuthService`, `PasswordResetService`, `EmailVerificationService`, `UserManagementService`, `RoleService`, `MenuService` | none |
| Authorization | `UserPolicy`, Spatie permissions (`view user`, `manage menu`, …) | `permission:` middleware on routes + `@can` in Blade + `$this->authorize()` in controllers |
| Identity payload | `AuthService::me()` (same shape as `GET /api/v1/auth/me`) | rendered into the dashboard |

Two small traits keep the controllers thin:

- `App\Http\Controllers\Web\Concerns\IssuesAuthCookie` — turns an auth payload into a cookie + redirect (login & register).
- `App\Http\Controllers\Web\Concerns\HandlesDomainErrors` — the backend's `BusinessRuleException` subclasses render JSON and policy denials throw `AuthorizationException`; this trait converts both into `back()->withErrors()`-style redirects, so domain failures surface as flash errors on the web.

---

## Routes (`routes/web.php`)

All web routes run through the `web` group (which now includes `AuthenticateWithJwtCookie`).

| Method | URI | Name | Middleware | Reuses |
|--------|-----|------|------------|--------|
| GET | `/login` | `login` | `web.guest` | — |
| POST | `/login` | `login.store` | `web.guest` | `LoginRequest`, `AuthService::login` |
| GET/POST | `/register` | `register` / `register.store` | `web.guest` | `RegisterRequest`, `AuthService::register` |
| GET/POST | `/forgot-password` | `password.request` / `password.email` | `web.guest` | `ForgotPasswordRequest`, `PasswordResetService` |
| GET/POST | `/reset-password/{token}` | `password.reset` / `password.update` | `web.guest` | `ResetPasswordRequest`, `PasswordResetService` |
| POST | `/logout` | `logout` | `web.auth` | `AuthService::logout` |
| GET | `/dashboard` | `dashboard` | `web.auth` | `AuthService::me` |
| GET/PUT | `/profile/password` | `profile.password.edit` / `…update` | `web.auth` | `ChangePasswordRequest`, `PasswordResetService::changePassword` |
| POST | `/email/verification-notification` | `verification.send` | `web.auth` | `EmailVerificationService` |
| GET | `/users` | `users.index` | `web.auth`, `permission:view user` | `UserManagementService::list`, `UserPolicy` |
| GET/POST | `/users/create`, `/users` | `users.create` / `users.store` | `permission:create user` | `StoreUserRequest`, `UserManagementService::create` |
| GET/PUT | `/users/{user}/edit`, `/users/{user}` | `users.edit` / `users.update` | `permission:edit user` | `UpdateUserRequest`, `UserManagementService::update` |
| DELETE | `/users/{user}` | `users.destroy` | `permission:delete user` | self-delete guard + `UserPolicy` + `UserManagementService::delete` |
| GET | `/menus`, `/menus/trashed` | `menus.index` / `menus.trashed` | `permission:view menu` | `MenuService::adminList` / `trashedList` |
| GET/POST | `/menus/create`, `/menus` | `menus.create` / `menus.store` | `permission:manage menu` | `StoreMenuRequest`, `MenuService::create` |
| GET/PUT | `/menus/{menu}/edit`, `/menus/{menu}` | `menus.edit` / `menus.update` | `permission:manage menu` | `UpdateMenuRequest`, `MenuService::update` |
| DELETE | `/menus/{menu}` | `menus.destroy` | `permission:manage menu` | `MenuService::delete` (`cascade` button) |
| POST | `/menus/{menu}/restore` | `menus.restore` | `permission:restore menu` | `MenuService::restore` (`withTrashed` binding) |

> **Route-name note:** the global Playground demo previously registered the generic names `users.*`. To free those for the web admin UI, it is now `Route::apiResource('users', …)->names('playground.users')` (URLs unchanged; only the internal names became `playground.users.*`).

---

## Views and components

Assets are loaded from CDN (matching the AdminLTE 4 reference pages). Layout is component-driven and DRY.

```
resources/views/
├── components/
│   ├── layouts/app.blade.php       ← <x-layouts.app>  authenticated shell (navbar + sidebar + footer)
│   ├── layouts/auth.blade.php      ← <x-layouts.auth> centered login card
│   ├── partials/                   ← assets-head, assets-scripts (shared), navbar, sidebar, footer
│   ├── nav/tree.blade.php          ← recursive renderer for the dynamic sidebar tree
│   ├── alert.blade.php             ← flash + validation feedback
│   ├── card.blade.php              ← panel wrapper (title + actions slot)
│   └── form/                       ← input, select, multiselect, checkbox (label + value + @error in one place)
├── auth/                           ← login, register, forgot-password, reset-password
├── profile/password.blade.php
├── dashboard.blade.php
├── users/                          ← index, create, edit, _form (shared)
└── menus/                          ← index, trashed, create, edit, _form (shared)
```

- **Form components** (`<x-form.input>`, `<x-form.select>`, `<x-form.multiselect>`, `<x-form.checkbox>`) encapsulate the label, `old()` repopulation, Bootstrap `is-invalid` state, and the `@error` message — so individual forms stay declarative and consistent.
- **Create/edit forms** share one `_form.blade.php` partial per resource (DRY), differing only in `action`/`method`/`submitLabel`.
- The **sidebar is fully data-driven by the [dynamic navigation engine](./navigation-menu.md)** — no hardcoded links. `App\View\Composers\NavigationComposer` calls the *same* `MenuService::navigationTree()` the API's navigation endpoint uses (single source of truth), and `<x-nav.tree>` renders the result recursively (`h` → section label, `p` → collapsible branch, `c` → leaf link). Per-viewer visibility (auth/guest flags, role attachments, user include/exclude) is resolved by the engine, so the Blade has **no `@can` gating** — a node only reaches the view if the viewer may see it. Default items are seeded by `MenuSeeder` (Dashboard, User Management, Menu Navigation) and are editable through the menu management UI.
  - **Caveat — gating is by role, not permission.** The engine filters menu visibility via attached *roles* (`hasAnyRole`), whereas page authorization gates by *permission* (`permission:` middleware). The seeder attaches the roles that currently hold `view user` / `view menu` (Root/Admin/Moderator/Management) so the two line up, but if you later re-grant those permissions to different roles, update the menu's role attachments too — otherwise a sidebar link can show (or hide) out of sync with the route it points at. The route middleware still enforces real authorization regardless, so this is a cosmetic mismatch, never a security hole.
  - Menu `icon` stores a **full** icon class (e.g. `bi bi-people`); `route` stores a **registered route name** (e.g. `dashboard`), and unregistered names degrade to `#`.

All forms are standard `<form method="POST">` submissions (with `@csrf`, and `@method('PUT'|'DELETE')` where needed) that post to the web controllers above.

---

## Local usage

```bash
php artisan migrate --seed     # seeds admin@example.com / 123456789 (Admin)
php artisan serve
```

Visit `/login`, sign in with the seeded admin, and the dashboard, user management, and menu management screens become available according to your permissions.

---

## Related documentation

- [JWT Authentication & RBAC](./jwt-authentication.md) — the JWT guard the cookie bridge reuses
- [User Management & H-RBAC](./user-management-hrbac.md) — policies/hierarchy enforced identically on the web
- [Dynamic Navigation Menu](./navigation-menu.md) — menu domain reused by the web menu screens
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — layer responsibilities the web controllers respect
- [API Routing](./api-routing.md) — global vs versioned API surface the web layer sits beside
