# Web Frontend (Blade + AdminLTE 4)

This application is **API-first**. The server-rendered **Blade** admin frontend (AdminLTE 4 — Bootstrap 5 + Bootstrap Icons) is a **pure consumer of the versioned API**: every web controller forwards the request to `/api/v1/*` and only turns the JSON envelope into a Blade view or a redirect. **No business logic lives in the web layer** — validation, CRUD, and RBAC all run exactly once, behind the API. The web controllers "only receive and send data".

```
Browser ──► Web controller ──► ApiClient (Http::withToken) ──► /api/v1/*  ──► Services/Policies/FormRequests
   ▲                                                                │
   └──────────────  Blade view / redirect  ◄──── JSON envelope ◄────┘
```

> The backend is the single source of truth. See [JWT Authentication & RBAC](./jwt-authentication.md), [User Management & H-RBAC](./user-management-hrbac.md), [Dynamic Navigation Menu](./navigation-menu.md), [CRUD Architecture](./crud-architecture.md), and [Localization](./localization.md) (all user-facing copy must use `__()` / translatable fields — see the **Task rule** there).

---

## Authentication model: stateless JWT in an HttpOnly cookie

The API is stateless JWT and intentionally has **no session login route**. The browser carries the **same** JWT the API issues, stored in an **HttpOnly cookie**, and the web layer forwards it back to the API as a `Bearer` header on every call:

```
POST /login ──► LoginController ──► POST /api/v1/auth/login   (API validates + issues JWT)
                                          │
                                          ▼
                          Set HttpOnly cookie `auth_token` = JWT
                                          │
Every later web request ──► AuthenticateWithJwtCookie ──► binds the JWT to the `api` guard
                                          │
                          web.auth / web.guest decide redirects; ApiClient re-reads the
                          cookie and forwards it as `Authorization: Bearer …` to /api/v1/*
```

| Piece | File | Role |
|-------|------|------|
| Cookie factory | `app/Support/Auth/AuthCookie.php` | Builds/forgets the HttpOnly `auth_token` cookie (lifetime mirrors the token's `expires_in`) |
| Guard bridge | `app/Http/Middleware/AuthenticateWithJwtCookie.php` | Appended to the `web` group; reads the cookie and resolves the `api` guard so `web.auth`/`web.guest` know who is logged in. Never rejects — a bad/expired token = guest |
| Auth gate | `app/Http/Middleware/EnsureWebAuthenticated.php` (alias `web.auth`) | Redirects guests to `login` (stores intended URL) |
| Guest gate | `app/Http/Middleware/RedirectIfWebAuthenticated.php` (alias `web.guest`) | Bounces authenticated users away from login/register/reset |

**Login/register** forward credentials to `POST /api/v1/auth/{login,register}` and persist the returned `data.token` in the cookie (`IssuesAuthCookie`). **Logout** calls `POST /api/v1/auth/logout` (the API blacklists the JWT) and forgets the cookie regardless of the API result.

The server keeps **no session-based identity**; the session is used only for CSRF, flash messages, and the "intended URL". Identity is always the stateless JWT, owned by the API.

---

## The API client: how the web layer talks to the backend

Two small pieces keep every web controller a thin, uniform forwarder:

| Piece | File | Role |
|-------|------|------|
| `ApiClient` | `app/Support/Api/ApiClient.php` | `Http` wrapper: targets `{current host}/api/v1`, sets `Accept: application/json`, and forwards the JWT from the `auth_token` cookie as `Bearer`. Exposes `get/post/put/delete` plus `postMultipart`/`putMultipart` for file uploads (flattens nested fields and attaches `attachments[]`; `putMultipart` uses `_method=PUT` spoofing). `withPrefix('api/playground')` returns a clone targeting the global playground surface (used by the order master-detail screens). |
| `ConsumesApi` (trait) | `app/Http/Controllers/Web/Concerns/ConsumesApi.php` | `respondFromApi($request, …)` maps the JSON envelope: **2xx** → redirect or inline success; **422** → field errors; **401/419** → login. Pass `stayOnPageOnSuccess: true` when the user should remain on the same screen (edit save, change password, forgot-password link, resend verification). Default `false` for create/delete/login/logout. |
| AJAX forms | `x-adminlte4::form.ajax`, `x-adminlte4::form.submit`, `x-adminlte4::form.error`, `adminlte4/components/partials/ajax-form` | All Blade forms submit via `fetch` + `Accept: application/json`; validation errors render inline without reload. |
| `IssuesAuthCookie` (trait) | `app/Http/Controllers/Web/Concerns/IssuesAuthCookie.php` | Turns the API's auth payload (`data.token` / `expires_in`) into the cookie + redirect (login & register). |

Because validation lives only in the API, web controllers **do not use Form Requests** — they forward the raw input (`$request->except(['_token','_method'])`) and let the API's Form Request validate and return `422`, which `ConsumesApi` translates into the usual `$errors` bag + repopulated `old()` input. There is **no `$this->authorize()` and no `permission:` middleware on web routes**: the API authorizes every forwarded call.

### Two deliberate exceptions (reads with no business logic)

These are pure reference reads, not business logic, and there is no suitable API endpoint, so they are read directly. Flagged here for honesty:

1. **Sidebar and navbar navigation** (`App\View\Composers\NavigationComposer`) calls `MenuService::navigationTree(..., layout: …)` directly — `sidebar` for the sidebar partial, `navbar` for the top bar. It runs on **every** page render; routing it through a self-HTTP call would add a round-trip per page for zero logic gain. Same engine as `GET /api/v1/navigation/menus?layout=…`.
2. **Menu form option-lists** (`roles`, `users` pickers in `Web\Navigation\MenuController::renderForm`) are read via Eloquent because the API exposes no "list all roles / all users" endpoint. If full purity is required, add those endpoints and switch the form to consume them.

---

## Routes (`routes/web.php`)

All web routes run through the `web` group (which includes `AuthenticateWithJwtCookie`). They carry **no `permission:` gate** — the API authorizes each forwarded call; web middleware is transport-only.

| Method | URI | Name | Middleware | Forwards to (API) |
|--------|-----|------|------------|-------------------|
| GET | `/` | — | `web` | redirects to `dashboard` if the `api` guard resolves, else `login` |
| GET/POST | `/login` | `login` / `login.store` | `web.guest` | `POST /auth/login` |
| GET/POST | `/register` | `register` / `register.store` | `web.guest` | `POST /auth/register` |
| GET/POST | `/forgot-password` | `password.request` / `password.email` | `web.guest` | `POST /auth/forgot-password` |
| GET/POST | `/reset-password/{token}` | `password.reset` / `password.update` | `web.guest` | `POST /auth/reset-password` |
| POST | `/logout` | `logout` | `web.auth` | `POST /auth/logout` |
| GET | `/locale/{locale}` | `locale.switch` | `web` | sets locale cookie; persists `users.locale` when JWT-authenticated; redirects back (see [Localization](./localization.md#switching-locale)) |
| GET | `/dashboard` | `dashboard` | `web.auth` | `GET /auth/me` |
| GET/PUT | `/profile/password` | `profile.password.edit` / `…update` | `web.auth` | `PUT /auth/password` |
| POST | `/email/verification-notification` | `verification.send` | `web.auth` | `POST /auth/email/verification-notification` |
| GET | `/users` | `users.index` | `web.auth` | Blade shell for the user DataTable |
| GET | `/users/datatable` | `users.datatable` | `web.auth` | `GET /users?display_mode=datatable` (proxied) |
| GET/POST | `/users/create`, `/users` | `users.create` / `users.store` | `web.auth` | `GET /users/assignable-roles`, `POST /users` |
| GET/PUT | `/users/{user}/edit`, `/users/{user}` | `users.edit` / `users.update` | `web.auth` | `GET /users/{id}`, `PUT /users/{id}` |
| DELETE | `/users/{user}` | `users.destroy` | `web.auth` | `DELETE /users/{id}` |
| GET | `/orders` | `orders.index` | `web.auth` | Blade shell for the order DataTable |
| GET | `/orders/datatable` | `orders.datatable` | `web.auth` | `GET /playground/orders?display_mode=datatable` (proxied) |
| GET/POST | `/orders/create`, `/orders` | `orders.create` / `orders.store` | `web.auth` | `POST /playground/orders` (master-detail payload) |
| GET | `/orders/{order}` | `orders.show` | `web.auth` | `GET /playground/orders/{id}` (master + lines) |
| GET/PUT | `/orders/{order}/edit`, `/orders/{order}` | `orders.edit` / `orders.update` | `web.auth` | `GET`/`PUT /playground/orders/{id}` |
| DELETE | `/orders/{order}` | `orders.destroy` | `web.auth` | `DELETE /playground/orders/{id}` |
| DELETE | `/orders/bulk` | `orders.bulk-destroy` | `web.auth` | `DELETE /playground/orders/bulk` (mass-delete; `ids[]` in body) |
| GET | `/navigation/tree` | `navigation.tree` | `web.auth` | `GET /navigation/menus` (optional `?q=`) |
| GET | `/menus`, `/menus/trashed` | `menus.index` / `menus.trashed` | `web.auth` | `GET /navigation/menus/{list,trashed}` |
| GET/POST | `/menus/create`, `/menus` | `menus.create` / `menus.store` | `web.auth` | `GET /navigation/menus/list`, `POST /navigation/menus` |
| GET/PUT | `/menus/{menu}/edit`, `/menus/{menu}` | `menus.edit` / `menus.update` | `web.auth` | `GET/PUT /navigation/menus/{id}` |
| DELETE | `/menus/{menu}` | `menus.destroy` | `web.auth` | `DELETE /navigation/menus/{id}` (`cascade`) |
| POST | `/menus/{menu}/restore` | `menus.restore` | `web.auth` | `POST /navigation/menus/{id}/restore` |
| GET | `/notifications` | `notifications.index` | `web.auth` | `GET /notifications?limit=100` |
| GET | `/notifications/unread-count`, `/notifications/recent` | `notifications.unread-count` / `notifications.recent` | `web.auth` | `GET /notifications/unread-count`, `GET /notifications?limit=10` (bell JSON) |
| POST | `/notifications/read-all`, `/notifications/{id}/read` | `notifications.read-all` / `notifications.read` | `web.auth` | `POST /notifications/read-all`, `POST /notifications/{id}/read` |

> The navbar bell (`@can('view notification')`) polls or receives realtime pushes
> via Laravel Echo (Vite bundle); see [Activity notifications](./notifications.md#realtime-vs-polling).

> **Route-name note:** the global Playground demo previously registered the generic names `users.*`. To free those for the web admin UI, it is now `Route::apiResource('users', …)->names('playground.users')` (URLs unchanged).

---

## Views and components

Assets are loaded from CDN for AdminLTE/Bootstrap. **Notification bell** scripts
are bundled with Vite (`resources/js/notifications/bell.js`, `@vite` in the navbar).
Run `npm run build` before deploying (or `npm run dev` alongside `php artisan serve`).
Views render the **JSON arrays** returned by the API (e.g. `$profile`, `$menu['name']` for display, `$menu['name_translations']` for edit forms), not Eloquent models. Static UI strings use `__('…')` from `lang/{locale}/web/*` and `lang/{locale}/ui.php` — never hardcode copy in Blade.

### Layout namespace and `config/web.php`

The active layout is **`config('web.layout')`** (env `WEB_LAYOUT`, default `adminlte4`). It names both the view directory (`resources/views/{layout}/`) and the anonymous Blade component prefix (`<x-{layout}::…>`).

| Piece | File | Role |
|-------|------|------|
| Config | `config/web.php` | `layout` key — switch layouts without touching controllers |
| Resolver | `App\Support\Web\WebLayout` | `view()`, `componentsPath()`, `breadcrumbsView()`, `sidebarComposerView()`, `navbarComposerView()` |
| Controllers | `App\Http\Controllers\Web\Controller` + `RendersWebViews` | `$this->webView('dashboard')` → `view('{layout}.dashboard')` |
| Bootstrap | `AppServiceProvider` | Registers `Blade::anonymousComponentPath(WebLayout::…)` and `NavigationComposer` on the layout sidebar **and** navbar |

**AdminLTE 4** is the shipped layout today (`resources/views/adminlte4/`). Blade templates inside that tree still reference `<x-adminlte4::layouts.app>`, `<x-adminlte4::form.input>`, etc. — component tags are layout-specific by design.

When you add a second layout, create `resources/views/<layout>/` with its own components, set `WEB_LAYOUT=<layout>` (or override per environment), and keep using `$this->webView('…')` in controllers. The API layer stays unchanged.

`NavigationComposer` is bound to the active layout's sidebar and navbar via `View::composer(WebLayout::sidebarComposerView(), …)` and `View::composer(WebLayout::navbarComposerView(), …)` in `AppServiceProvider`. That resolves the **hashed** anonymous-component view namespace Laravel registers for `Blade::anonymousComponentPath()` (not the readable `<x-{layout}::…>` prefix). Breadcrumb markup uses `WebLayout::breadcrumbsView()`.

```
resources/views/adminlte4/
├── components/
│   ├── layouts/app.blade.php       ← <x-adminlte4::layouts.app>  authenticated shell (navbar + sidebar + footer)
│   ├── layouts/auth.blade.php      ← <x-adminlte4::layouts.auth> centered login card
│   ├── partials/                   ← assets-head, assets-scripts, navbar, sidebar, footer,
│   │                                  datatable-assets, datatable-row-actions
│   ├── nav/tree.blade.php          ← recursive renderer for the dynamic sidebar tree
│   ├── nav/bar-tree.blade.php      ← horizontal renderer for the dynamic navbar tree
│   ├── alert.blade.php             ← flash + validation feedback
│   ├── card.blade.php              ← panel wrapper (title + actions slot)
│   └── form/                       ← input, select, multiselect, checkbox, translatable-input
├── partials/
│   └── breadcrumbs.blade.php       ← AdminLTE breadcrumb `<ol>` renderer
├── auth/                           ← login, register, forgot-password, reset-password
├── profile/password.blade.php
├── dashboard.blade.php             ← consumes GET /auth/me payload
├── users/                          ← index (datatable shell), create, edit, _form (shared)
├── orders/                         ← master-detail demo: index (datatable), create, edit, show, _form (dynamic line rows)
├── menus/                          ← index, trashed, create, edit, _form (shared)
└── notifications/                  ← index (full list); navbar bell lives in components/partials/navbar
```

- **Create/edit forms** share one `_form.blade.php` per resource. Validation errors come from the API's `422` (mapped by `ConsumesApi`), repopulated with `old()`.
- **Menu placement** (`position` + `reference_id`) is optional on both create and edit. Empty selects mean append on create or unchanged order on edit — see [navigation-menu.md](./navigation-menu.md#request-fields-storeupdate).
- **Menu translatable fields** — `name` and `description` use `<x-adminlte4::form.translatable-input>` (one field per locale). Create/edit posts `name[en]`, `name[id]`, … which the web controller forwards as JSON to the API. Edit forms bind from `name_translations` / `description_translations` on the API detail payload.
- **Locale switcher** — navbar dropdown links to `GET /locale/{locale}` (`en`, `id`). Flash and AJAX fallback messages come from `lang/{locale}/ui.php` (`ui.ajax.*`).
- The **sidebar and navbar are data-driven** by the [dynamic navigation engine](./navigation-menu.md), scoped per `layout` key (`sidebar` / `navbar`). `NavigationComposer` feeds `MenuService::navigationTree()` (see exception #1 above). Sidebar uses `<x-adminlte4::nav.tree>`; navbar uses `<x-adminlte4::nav.bar-tree>`. Visibility is resolved by the engine — **no `@can` gating** in the tree renderers.
- **Navigation cache** — the tree comes from a server-side snapshot (`menus.navigation.snapshot`); see [Caching](./navigation-menu.md#caching). Any menu create/update/delete/restore or pivot sync **busts** the cache; the **next** `navigationTree()` call rebuilds it from the DB. The sidebar on an already-loaded page still holds the previous tree in HTML/embedded JSON until a full page reload.
- **Sidebar live-search** — the search box in `adminlte4/components/partials/sidebar-nav-search.blade.php` filters that **page-load** tree in-memory on each keystroke (same rules as `NavigationTreeFilter`). Matching leaves keep their ancestor headers/parents visible as markers; non-matching siblings are hidden. `GET /navigation/tree?q=` (web → API proxy) returns a freshly cached tree from the server if you need a live JSON refetch.
  - **Caveat — sidebar gating is by role, not permission.** The engine filters by attached *roles*; page access is enforced by the *API*. The seeder attaches the roles that hold `view user` / `view menu` so they line up; re-grant the permission to a different role and you must update the menu's role attachments too. Cosmetic only — the API still enforces real authorization.
  - Menu `icon` stores a **full** icon class (e.g. `bi bi-people`); null/empty renders the `bi bi-circle` placeholder. `route` stores a **registered route name**; unregistered names degrade to `#`.

All forms are standard `<form method="POST">` submissions (`@csrf`, `@method('PUT'|'DELETE')`) posting to the web controllers, which forward to the API.

### Breadcrumbs (`diglactic/laravel-breadcrumbs`)

Breadcrumb trails are **defined once per route name** in `routes/breadcrumbs.php` (loaded automatically by the package), not hardcoded in each view. The authenticated shell (`adminlte4/components/layouts/app.blade.php`) auto-renders the trail for the **current route** via `Breadcrumbs::view('adminlte4.partials.breadcrumbs')` (guarded by `Breadcrumbs::exists()`, so routes without a trail render nothing). `adminlte4/partials/breadcrumbs.blade.php` keeps the original AdminLTE markup (`<ol class="breadcrumb float-sm-end">`).

| Piece | File | Role |
|-------|------|------|
| Trail definitions | `routes/breadcrumbs.php` | One `Breadcrumbs::for('<route.name>', …)` per screen; `parent()` chains to the ancestor, `push()` adds the current crumb |
| Renderer | `resources/views/adminlte4/partials/breadcrumbs.blade.php` | Turns the generated collection into the `float-sm-end` `<ol>` |
| Auto-render | `resources/views/adminlte4/components/layouts/app.blade.php` | `@if (Breadcrumbs::exists()) {{ Breadcrumbs::view(\App\Support\Web\WebLayout::breadcrumbsView()) }} @endif` |

- **Localization** — every title goes through `__()` against the **same** lang keys the views already used (`menu.dashboard`, `web/*.breadcrumb`, `ui.breadcrumb.add`/`edit`, `web/menus.trashed_breadcrumb`, `web/profile.password_title`, `web/notifications.breadcrumb`). No new keys, all `en`/`id`/`ja` already covered. Never hardcode crumb copy.
- **Dynamic segment** — `orders.show` needs the order number (no static label). Its trail takes a parameter; the view feeds it **without a DB lookup** (the web layer is a pure API consumer) via `@php(Breadcrumbs::setCurrentRoute('orders.show', data_get($order, 'order_number')))` before the layout renders.
- **Adding a screen** — register a new `Breadcrumbs::for('<route.name>', …)` keyed by the route name; the layout picks it up with no view changes. Views no longer pass a `<x-slot:breadcrumb>`.

### Server-side DataTables (user list)

The user list uses **Yajra DataTables** server-side. The web layer does **not** build the payload — `Web\UserController::datatable()` **proxies** `GET /api/v1/users?display_mode=datatable` (forwarding the DataTables query params + Bearer), then rewrites each row's allowed actions onto web routes:

- The **API decides which actions** the actor may take (RBAC) and returns them as `actions[].key` (`show`/`edit`/`delete`).
- The web layer **maps those keys** to clickable web routes (`users.edit` GET, `users.destroy` DELETE form) with `bi bi-*` icons, dropping `show` (there is no web detail page).
- On any API failure the proxy returns an **empty** DataTables payload.

Action buttons stay web-side because the browser submits a normal CSRF `<form>` (with `_method`) to a **web** route — the API (Bearer-only, no CSRF) is never hit directly from the browser.

| File | Role |
|------|------|
| `app/Http/Controllers/Web/UserController.php` | `datatable()` proxies the API and maps actions; `index()` renders the shell |
| `resources/views/adminlte4/users/index.blade.php` | Empty `<table>` + DataTables init pointing at `users.datatable` |
| `resources/views/adminlte4/components/partials/datatable-row-actions.blade.php` | `renderDatatableActions()` + CSRF form submit for non-GET actions |
| `tests/Feature/Http/Controllers/Web/SidebarNavigationWebTest.php` | Asserts `NavigationComposer` binds to the hashed sidebar/navbar views and seeded layout menus render in the HTML shell |
| `tests/Feature/Http/Controllers/Web/UserDatatableTest.php` | `Http::fake()` → asserts the proxy maps API keys to web URLs |

---

## Caveat: self-calls require a concurrent web server

Because the web layer makes **real HTTP round-trips to its own host**, the server must be able to handle a second concurrent request while serving the first. Laragon (Apache/Nginx + multiple PHP workers) does this out of the box. For `php artisan serve` (single worker by default), set in `.env`:

```dotenv
PHP_CLI_SERVER_WORKERS=4
```

Otherwise the single worker **deadlocks** waiting on itself.

---

## Local usage

```bash
php artisan migrate --seed     # seeds admin@example.com / 123456789 (Admin)
php artisan serve              # ensure PHP_CLI_SERVER_WORKERS>=2 in .env (see caveat)
```

Visit `/login`, sign in with the seeded admin, and the dashboard / user management / menu management screens become available according to your permissions (enforced by the API).

---

## Related documentation

- [JWT Authentication & RBAC](./jwt-authentication.md) — the JWT guard the cookie bridge reuses
- [User Management & H-RBAC](./user-management-hrbac.md) — policies/hierarchy enforced by the API the web calls
- [Dynamic Navigation Menu](./navigation-menu.md) — menu domain behind the web menu screens
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — layer responsibilities behind the API
- [API Routing](./api-routing.md) — the versioned API surface the web layer consumes
- [Localization](./localization.md) — locale resolution, `lang/` layout, Spatie fields, task rule for translations
