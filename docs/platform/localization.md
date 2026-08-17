# Localization (multi-language)

Cookie- or user-preference-driven locale for API, Blade, and DB content. **No route prefix** (`/en/...`). The sibling Vue-Workers SPA has its own JSON locale runtime and startup resolution; its contract lives in [../integration/vue-workers-spa.md](../integration/vue-workers-spa.md).

## Architecture

| Layer | Tool | Use for |
|-------|------|---------|
| Package defaults | `laravel-lang/common` | `auth.php`, `validation.php`, … |
| Application strings | `lang/{locale}/…` | `ui.php`, `web/*`, `api/*`, `menu.php`, … |
| Database content | Spatie translatable | JSON fields (`Menu.name`, …) |
| Vue-Workers SPA | `monolith-vue-workers/src/i18n/locales/{en,id,ja}.json` | SPA labels, errors, headings, statuses, and contextual UI copy |

**Never** put app strings in package-managed files (`lang:update` overwrites them).

`localization.md` owns Laravel PHP locale files, API/Blade resolution, and parity rules. [`vue-workers-spa.md`](../integration/vue-workers-spa.md) owns SPA JSON keys, runtime switching, module-level fallback translation, and Worker hydration. Keep those contracts linked instead of duplicating them.

### Task rule (required)

Any user-facing copy change includes **all** supported locales (`en`, `id`, `ja`) in the **same** task/PR.

Every translation must be contextual, not literal. Review copy against its surface (button, heading, toast, validation, status, or explanatory text), audience, domain glossary, and locale-specific grammar/register. Preserve placeholders, plural behavior, and machine-contract text exactly where required; split keys when one source phrase serves different UI contexts. Literal wording that sounds unnatural in Indonesian or Japanese is not accepted.

Translation workflow:

1. Keep English as the reference/source locale unless key design or context separation requires a source update.
2. Identify surface, audience, domain term, register, placeholders, and plural rules before translating.
3. Review Indonesian and Japanese for natural product language, not word-for-word equivalence.
4. Run locale parity and placeholder tests before review; test locale switching where copy is reactive.
5. Update affected domain documentation when terminology or translation workflow changes.

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

Authenticated clients may also call `POST /api/v1/auth/locale`. Resolution order remains db → cookie → default (**not** `Accept-Language` alone).

### SPA startup resolution

Vue-Workers resolves its initial locale independently of Laravel middleware:

1. Supported `localStorage.locale`.
2. First two characters of supported `navigator.language`.
3. `en`.

Runtime `setLocale()` persists the SPA choice and updates the HTML `lang` attribute. `window` and `document` access is guarded for SSR and tooling contexts. Authenticated SPA flows may synchronize the choice through `POST /api/v1/auth/locale`, but backend resolution remains user preference → cookie → app default. Do not document the two startup paths as one shared precedence chain.

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
- `tests/Unit/Localization/LocaleParityTest.php` checks supported locale directories, application-owned file presence, dot-notation key parity, and PHP placeholder parity. It excludes files listed in `config/localization.php` / package-managed basenames, including `validation.php` from placeholder comparison.
- Vue-Workers `npm run check:i18n` checks JSON key parity, Vue interpolation tokens, malformed braces, suspicious English duplicates, and hardcoded fallback-copy patterns under `src/`. Duplicate warnings are review input; technical terms, brands, codes, and machine-contract strings may remain unchanged.

## Number & date display (not lang files)

User-facing **numbers and datetimes** (money separators, absolute dates, relative “2 hours ago”) follow `app()->getLocale()` via `App\Support\Presentation\{Date,Money,Byte}Presenter` at the API Resource layer. They are **not** translated through `lang/{locale}/*` string keys. API `*_formatted` and `*_human` companions remain authoritative for API resources and Blade.

SPA-native values that are not already formatted by the API use `Intl.NumberFormat(locale.value)` and `Intl.DateTimeFormat(locale.value, …)` with the active Vue locale. Do not call unqualified `toLocaleString()` for user-facing values.

Blade must not reformat API values — consume Resource companions (`*_formatted`, `*_human`). Contract: [platform/crud.md — API Resource presentation](crud.md#api-resource-presentation-dual-fields).

## Related

- [web/blade-admin.md](../web/blade-admin.md)
- [integration/vue-workers-spa.md](../integration/vue-workers-spa.md) — SPA JSON locales, runtime switching, fallback translation, hydration, and `useI18n()` context rule
- [domains/navigation/README.md](../domains/navigation/README.md) — Spatie menu fields
