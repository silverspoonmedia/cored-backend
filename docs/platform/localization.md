# Localization (multi-language)

Cookie- or user-preference-driven locale for API and DB content, with **no route prefix**. Browser UI strings live in the SPA's own JSON locales, not here.

**Canonical supported locales:** backend codes `en`, `id` — every other doc links here instead of restating the list. English is the source; Indonesian is the one translated locale. Backend resolution order is `users.locale` → `X-SPA-Locale` header → `locale` cookie → `config('app.locale')`, applied by the `SetLocale` middleware on `web` and `api`. Application strings live in `lang/{locale}/*.php`; package-managed files are curated too but `lang:update` overwrites them, and a nested group re-added to `lang/{locale}.json` shadows the PHP namespaces (a build failure). Every user-facing copy change lands in **both** locales in the same task with each curated key flagged in place, and settled term choices come from the glossary.

| | |
|---|---|
| Code | `vendor/silverspoonmedia/platform/src/Support/Localization/**`, `vendor/silverspoonmedia/platform/src/Http/Middleware/SetLocale.php`, `app/Domain/*/Http/Requests/Concerns/*` |
| Config | `config/localization.php`, `config/app.php` (`locale`) |
| API | `POST /api/v1/auth/locale`; `X-SPA-Locale` request header |
| SPA | `cored-frontend/src/i18n/locales/*.json`, `src/i18n/curation-log.json` |
| Commands | `locale:add`, `lang:add`/`lang:update`, `php scripts/backend-locale-audit.php` |
| Tests | `LocaleParityTest`, `LocaleUsageTest`, `LocaleGuardTest`, SPA `npm run check:i18n` |
| Pakem | [i18n-lang.mdc](../../.cursor/rules/i18n-lang.mdc) · [i18n-guardrail.mdc](../../.cursor/rules/i18n-guardrail.mdc) |
| Glossary | [naming-conventions.md](naming-conventions.md) |
| Deep dive | [reference-localization.md](reference-localization.md) |
