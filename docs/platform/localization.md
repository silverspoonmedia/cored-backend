# Localization (multi-language)

Cookie- or user-preference-driven locale for API, Blade, and DB content. **No route prefix** (`/en/...`).

## Architecture

| Layer | Tool | Use for |
|-------|------|---------|
| Package defaults | `laravel-lang/common` | `auth.php`, `validation.php`, … |
| Application strings | `lang/{locale}/…` | `ui.php`, `web/*`, `api/*`, `menu.php`, … |
| Database content | Spatie translatable | JSON fields (`Menu.name`, …) |

**Never** put app strings in package-managed files (`lang:update` overwrites them).

### Task rule (required)

Any user-facing copy change includes **all** supported locales (`en`, `id`, `ja`) in the **same** task/PR.

| Touch | Translate in |
|-------|----------------|
| Blade / UI | `lang/{locale}/ui.php`, `web/*`, `menu.php` |
| API messages / policy / business exceptions | `lang/{locale}/api/*` |
| FormRequest messages | `forms/*` or `__()` in request |
| Translatable DB fields | Spatie + admin input per locale |
| Seed sample content | Locale maps in seed data |

Emails follow recipient `users.locale` (`HasLocalePreference` on `User`).

## Locale resolution

`SetLocale` on `web` and `api` (after JWT so `users.locale` is available):

1. `users.locale` (authenticated)
2. `locale` cookie
3. `config('app.locale')`

Switch (Blade): `GET /locale/{locale}` — cookie; authenticated also updates `users.locale`.

Both Blade (`Web\LocaleController`) and API (`POST /api/v1/auth/locale`) call `App\Support\Localization\LocalePreference::apply()` so cookie + user-column persist stay one implementation.

### Web ↔ API sync

Blade proxies via real HTTP (`ApiClient`). Web resolves locale first; `ApiClient` forwards it as the `locale` cookie so the API request does not fall back to English.

### Vue SPA

Client-driven: `localStorage` + cookie; authenticated `POST /api/v1/auth/locale`. Axios may send `Accept-Language`; Monolith still uses db → cookie → default (**not** Accept-Language alone). See [spa-gateway](../integration/spa-gateway.md).

## Folder layout (app-owned)

```
lang/{en,id,ja}/
  ui.php, menu.php, notifications.php, mail.php
  web/*.php
  api/*.php
  forms/*.php
```

Managed files listed in `config/localization.php` → `managed_files`.

## Key conventions

| Context | Example |
|---------|---------|
| Shared UI | `__('ui.actions.save')` |
| Blade area | `__('web/navbar.home')` |
| API domain | `__('api/navigation.created')` |
| Static nav labels | `__('menu.dashboard')` |
| DB menus | `$menu->name` (Spatie) |

## Packages & parity

- `php artisan lang:add …` / `lang:update` for managed packs
- `LocaleParityTest` fails if app-owned keys drift across locales

## Related

- [web/blade-admin.md](../web/blade-admin.md)
- [domains/navigation/README.md](../domains/navigation/README.md) — Spatie menu fields
