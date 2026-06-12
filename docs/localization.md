# Localization (multi-language)

Cookie- or user-preference-driven locale resolution for **API responses**, **Blade views**, and **database content**. There is **no route prefix** (`/en/...`, `/id/...`); the active locale is resolved per request by middleware.

---

## Architecture summary

| Layer | Tool | Use for |
|-------|------|---------|
| Laravel & package defaults | [`laravel-lang/common`](https://laravel-lang.com) | `auth.php`, `validation.php`, `pagination.php`, `passwords.php`, `actions.php`, `http-statuses.php`, `*.json` |
| Application file translations | `lang/{locale}/…` (application-owned) | `ui.php`, `menu.php`, `notifications.php`, `web/*`, `api/*` |
| Database content | [`spatie/laravel-translatable`](https://github.com/spatie/laravel-translatable) | Eloquent fields stored as JSON (`Menu.name`, `Menu.description`, …) |

**Golden rule:** never put application-owned strings in package-managed files. `php artisan lang:update` (also wired into `composer update`) may overwrite those files.

### Task rule (required)

Any change that introduces or modifies **user-facing copy** must include translations in the **same task/PR** — do not merge hardcoded strings and “translate later.”

| Touch this | Translate in the same task |
|------------|----------------------------|
| Blade / UI label, button, hint, confirm, DataTables / AJAX fallback | `lang/{locale}/ui.php`, `lang/{locale}/web/*`, or `lang/{locale}/menu.php` — **every** entry in `config('localization.supported_locales')` (currently `en` + `id`) |
| API success/error message, policy deny text, business-rule exception | `lang/{locale}/api/*` (or `forms/*` for reusable validation copy) — all supported locales |
| Reusable FormRequest validation message | `lang/{locale}/forms/{resource}.php` or `FormRequest::messages()` calling `__()` — all supported locales |
| Database field shown to end users (titles, labels, body text) | Spatie `HasTranslations` on the model + admin input for **each** locale (see [Blade translatable fields](#blade-translatable-fields-admin-forms)) |
| Seeder / factory sample content in translatable columns | Locale map in seed data, e.g. `['en' => '…', 'id' => '…']` |

**Out of scope for file-based `lang/` (document separately if you add them):** Laravel Lang managed files (`validation.php`, …), third-party package strings.

Outbound **email notifications are in scope** and follow the recipient's locale — see [Outbound email locale](#outbound-email-locale).

---

## Locale resolution

`App\Http\Middleware\SetLocale` runs on **both** `web` and `api` middleware groups (with priority **after** JWT/auth middleware so `users.locale` is available on authenticated requests).

Priority:

1. `users.locale` when the `api` guard has an authenticated user
2. `locale` cookie (`config('localization.cookie_name')`, default `locale`)
3. `config('app.locale')`

Supported locales are whitelisted in `config/localization.php` (`supported_locales`).

### Switching locale

```
GET /locale/{locale}
```

Sets the locale cookie and redirects back. Example: `/locale/id`.

| Caller | Persistence |
|--------|-------------|
| Guest | Cookie only (`locale`) |
| Authenticated user (`api` guard via JWT cookie) | Cookie **and** `users.locale` updated in the same request |

On the next login, `SetLocale` reads `users.locale` first, so the last choice survives across sessions. Guests still rely on the cookie until they sign in.

### Web ↔ API locale sync

The Blade frontend is a pure API consumer: every web controller forwards to `/api/v1/*` over a **real HTTP round-trip** (`ApiClient`). That second request runs its **own** `SetLocale`, so the locale must cross the boundary or the two layers desync (web renders `id`, API replies `en`).

`SetLocale` runs **first on the web request** and resolves the canonical locale (`users.locale` > `locale` cookie > `config('app.locale')`). `ApiClient` then forwards that resolved value to the API as the `locale` cookie (alongside the JWT bearer). Result: both layers always land on the same locale.

| Request | Web resolves (db > cookie > default) | Forwarded to API as `locale` cookie | API resolves |
|---------|--------------------------------------|--------------------------------------|--------------|
| Authenticated, `users.locale = id` | `id` (db) | `id` | `id` (db, JWT forwarded — same value) |
| Authenticated, `locale` null, cookie `id` | `id` (cookie) | `id` | `id` (cookie) |
| Guest, cookie `id` | `id` (cookie) | `id` | `id` (cookie) |

Without forwarding, the server-to-server API call carries no cookie, so the API collapses to `db > default` and silently drops to `en` whenever the locale comes from the cookie. The DB-first fallback is unchanged on both sides — forwarding only fixes the cookie tier.

> Implemented in `ApiClient::pending()` via `withCookies([LocaleCookie::name() => app()->getLocale()], <host>)`. Covered by `tests/Feature/Support/Api/ApiClientLocaleForwardingTest.php`.

### Outbound email locale

Emails follow the **recipient's** saved locale, not the locale of the request that triggered them. This matters because a forgot-password request is made by a **guest**: the only reliable language signal is the target user's `users.locale`.

`User` implements `Illuminate\Contracts\Translation\HasLocalePreference::preferredLocale()`, returning `users.locale` when it is a supported locale (else `null`, so Laravel uses the app default). The notification queue/mailer wraps rendering in that locale automatically.

| Trigger | Locale used for the email |
|---------|---------------------------|
| Forgot password (guest submits target's email) | target user's `users.locale` |
| Email verification (authenticated) | the user's `users.locale` |
| No / unsupported `users.locale` | `config('app.locale')` |

All mail copy goes through the translator — never hardcode strings in a notification's `toMail()`:

- Shared password-reset / verify-email lines resolve from the **laravel-lang JSON packs** (`lang/{locale}.json`).
- Project-specific lines (e.g. the reset API hint) live in the application-owned `lang/{locale}/mail.php`.

`ResetPasswordNotification` overrides only the **action URL** (points at `/api/v1/auth/reset-password`) and adds the API hint; every line is translated. Covered by `tests/Feature/Notifications/ResetPasswordNotificationLocaleTest.php`.

### Locale parity (enforced)

`tests/Unit/Localization/LocaleParityTest.php` fails the build if any **application-owned** lang file or key exists in one supported locale but not another — the silent mixed-language failure mode. It excludes laravel-lang managed files (`config('localization.managed_files')`) and `*.json` packs, which `php artisan lang:update` keeps in sync. Adding a new locale to `supported_locales` makes this test require a full translation set before the suite passes.

---

## Folder layout

```
lang/
  en/
    auth.php              # managed — laravel-lang
    pagination.php        # managed
    passwords.php         # managed
    validation.php        # managed
    actions.php           # managed
    http-statuses.php     # managed
    en.json               # managed
    ui.php                # application-owned — shared UI, AJAX fallbacks
    menu.php              # application-owned — static layout labels
    notifications.php     # application-owned — flash/toast copy
    mail.php              # application-owned — project-specific email copy (API hints, …)
    web/
      auth.php            # application-owned — auth screens + flash
      session.php         # application-owned
      users.php           # application-owned
      menus.php           # application-owned
      profile.php         # application-owned
      navbar.php          # application-owned
      dashboard.php       # application-owned
    api/
      auth.php            # application-owned
      users.php           # application-owned
      common.php          # application-owned — 401/403/404 helpers
      admin.php           # application-owned
      playground.php      # application-owned
      navigation.php      # application-owned
      orders.php          # application-owned (example domain)
      payments.php        # application-owned (example domain)
    forms/
      users.php           # application-owned (reusable validation copy)
  id/
    … (mirror structure)
```

Managed file list is documented in `config/localization.php` → `managed_files`.

---

## Translation key conventions

| Context | Example key | Notes |
|---------|-------------|-------|
| Shared Blade UI | `__('ui.actions.save')` | Buttons, labels reused across screens |
| Blade per area | `__('web/navbar.home')` | Screen- or component-specific copy |
| API domain messages | `__('api/navigation.created')` | Success/error strings in JSON responses |
| Static layout labels | `__('menu.dashboard')` | Hard-coded nav labels **not** from DB |
| Database menu names | `$menu->name` | Spatie translatable — **not** lang files |

### Naming for medium/large projects

- `ui.*` — cross-cutting UI chrome
- `web/{area}.*` — server-rendered pages (`web/users`, `web/menus`, …)
- `api/{domain}.*` — API response copy grouped by bounded context (`api/auth`, `api/navigation`, `api/orders`)
- `menu.*` — static sidebar/header labels only
- `notifications.*` — flash/toast copy
- `forms/{resource}.*` — reusable validation messages (`forms/users.role_not_assignable`, …)
- `web/{area}.*` — Blade page copy (`web/auth.login_title`, `web/users.datatable.empty`, …)

---

## Packages & commands

Packages are already required in `composer.json`:

- `laravel-lang/common`
- `spatie/laravel-translatable`

Initial locale install (already run once in this repo):

```powershell
php artisan lang:add id en
```

Refresh package-managed files after `composer update`:

```powershell
php artisan lang:update
```

`composer.json` runs `lang:update` automatically in `post-update-cmd`.

---

## Middleware

```php
// bootstrap/app.php (excerpt)
$middleware->web(append: [
    AuthenticateWithJwtCookie::class,
    SetLocale::class,
]);

$middleware->api(append: [
    SetLocale::class,
]);

$middleware->priority([
    AuthenticateWithJwtCookie::class,
    OptionalJwtAuthenticate::class,
    Authenticate::class,
    SetLocale::class,
]);
```

---

## Database translations (Menu example)

### Migration

`database/migrations/2026_06_11_000002_make_menus_translatable.php` converts `menus.name` from `string` to `json` and adds nullable `description` (`json`).

### Model

```php
use Spatie\Translatable\HasTranslations;

class Menu extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];
}
```

### Create / update

```php
// Single locale (uses active app locale)
Menu::create(['name' => 'Dashboard', …]);

// All locales at once
Menu::create([
    'name' => ['en' => 'Dashboard', 'id' => 'Dasbor'],
    'description' => ['en' => 'Main overview', 'id' => 'Ringkasan utama'],
]);

// Update one locale
$menu->setTranslation('name', 'id', 'Dasbor');
$menu->save();
```

### Read (active locale)

```php
app()->getLocale(); // set by SetLocale
$menu->name;        // translated string
$menu->getTranslations('name'); // ['en' => '…', 'id' => '…']
```

### API resource

`MenuListResource` exposes:

- `name` / `description` — resolved for the **current** locale
- `name_translations` / `description_translations` — full maps for admin edit forms

The navigation tree cache stores translation **maps** (`name_translations`) and resolves the display string at request time via `TranslationResolver`, so cached data stays locale-agnostic.

### Blade translatable fields (admin forms)

Admin screens that edit Spatie-backed columns use `<x-form.translatable-input>` (`resources/views/components/form/translatable-input.blade.php`). It renders one text field per supported locale (`name[en]`, `name[id]`, …). On **edit**, values come from `name_translations` / `description_translations` in the API payload — not from the resolved `name` string alone.

The menu form (`resources/views/menus/_form.blade.php`) is the reference implementation. The web controller forwards the nested array to the API unchanged (`ConsumesApi` + `ApiClient` as JSON).

When the client sends `name` as an object, `ValidatesTranslatableInput::validateTranslatableAllLocales()` requires a non-empty value for **every** supported locale (`name.en`, `name.id`, …). A plain string still sets only the active request locale (API shorthand).

---

## Validation strategy

1. **Generic Laravel rules** (`required`, `email`, `min`, …) — use package `validation.php` via `__('validation.*')` (automatic when you do not override).
2. **Request-specific messages** — `FormRequest::messages()`:

```php
public function messages(): array
{
    return [
        'name.required' => __('validation.required', ['attribute' => 'name']),
    ];
}
```

3. **Reusable application messages** — dedicated files under `lang/{locale}/api/` or optional `lang/{locale}/forms/`, **not** `validation.php`.
4. **Translatable model fields** — `ValidatesTranslatableInput` trait (`validateTranslatableString`, `validateTranslatableAllLocales` when the payload is a locale map); see `StoreMenuRequest` / `UpdateMenuRequest`.
5. **Custom closure rules** — call `__('api/navigation.invalid_route')` inside the closure (see `namedRouteRule()`).
6. **API exception rendering** — `bootstrap/app.php` maps JWT/auth/404 errors through `lang/{locale}/api/*` (not hardcoded English).
7. **Policies & business exceptions** — `UserPolicy`, `CannotDeleteSelfException`, navigation exceptions, etc. use `__()` so deny messages follow the request locale.

---

## Usage examples

### API controller

```php
return $this->successResponse($data, __('api/navigation.created'), 201);
```

### Blade

```blade
{{ __('ui.actions.save') }}
{{ __('web/dashboard.welcome', ['name' => $profile['name']]) }}
```

### FormRequest (translatable + custom route message)

See `app/Http/Requests/Navigation/StoreMenuRequest.php`.

---

## Safe updates (laravel-lang)

1. Keep custom strings **only** in application-owned files (`ui.php`, `web/*`, `api/*`, …).
2. Treat `auth.php`, `validation.php`, `pagination.php`, `passwords.php`, `actions.php`, `http-statuses.php`, and `*.json` as **managed** — do not edit unless you accept merge risk on `lang:update`.
3. Run `php artisan lang:update` after `composer update` (automated via `post-update-cmd`).
4. Commit managed file changes separately from feature work so diffs stay reviewable.
5. For intentional overrides of a package string, copy the single key into an application-owned file and reference that key from code instead of patching `validation.php`.

---

## Configuration

| File / env | Purpose |
|------------|---------|
| `config/localization.php` | Supported locales, cookie name, user column, managed file list |
| `LOCALE_COOKIE` | Cookie name (default `locale`) |
| `LOCALE_COOKIE_MINUTES` | Cookie lifetime (default 1 year) |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | Default and fallback locale |
| `users.locale` | Per-user preference (nullable); migration `2026_06_11_000001_add_locale_to_users_table.php` |

### Key implementation files

| Area | Location |
|------|----------|
| Locale middleware | `app/Http/Middleware/SetLocale.php` |
| Web → API locale forwarding | `app/Support/Api/ApiClient.php` (`pending()` forwards `locale` cookie) |
| Recipient-locale email | `app/Models/User.php` (`preferredLocale()`), `app/Notifications/Auth/ResetPasswordNotification.php` |
| Application-owned mail copy | `lang/{locale}/mail.php` |
| Locale parity guard (test) | `tests/Unit/Localization/LocaleParityTest.php` |
| Cookie helper / switch route | `app/Support/Localization/LocaleCookie.php`, `GET /locale/{locale}` → `Web\LocaleController` |
| Translation resolver (cached nav) | `app/Support/Localization/TranslationResolver.php` |
| Translatable validation trait | `app/Support/Localization/Concerns/ValidatesTranslatableInput.php` |
| Menu JSON columns | `database/migrations/2026_06_11_000002_make_menus_translatable.php` |
| Menu model | `app/Models/Menu.php` (`HasTranslations`) |
| API resources | `app/Http/Resources/Navigation/MenuListResource.php` |
| Navigation cache (locale maps) | `app/Repositories/Eloquent/MenuRepository.php` (`name_translations` in snapshot) |
| Blade translatable control | `resources/views/components/form/translatable-input.blade.php` |
| Post-update lang sync | `composer.json` → `post-update-cmd` runs `php artisan lang:update` |

---

## Related documentation

- [Dynamic navigation menu](./navigation-menu.md) — Menu model, API, translatable names
- [Web frontend](./web-frontend.md) — Blade consumer, locale switcher in navbar
- [API routing](./api-routing.md) — versioned API surface
