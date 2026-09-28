# Localization — reference

Detail behind [localization.md](localization.md): architecture, locale resolution, folder layout, key conventions, packages & parity, curation tooling, settings labels, and number/date display. Settled terms live in [naming-conventions.md](naming-conventions.md).

Cookie- or user-preference-driven locale for API and DB content. **No route prefix** (`/en/...`). The sibling Vue-Workers SPA has its own JSON locale runtime; see sibling [i18n.md](../../../monolith-vue-workers/docs/i18n.md). Cross-repo pointer: [vue-workers-spa.md](../integration/reference-vue-workers-spa.md#i18n-porting-notes).

**Canonical supported locales:** backend codes `en`, `id`, `ja`, `ko`, `zh_CN`, `ar`, `ru` (SPA/browser: `zh-CN` ↔ backend `zh_CN`). All other docs link here instead of restating the list.

## Architecture

| Layer | Tool | Use for |
|-------|------|---------|
| Package defaults | `laravel-lang/common` | `auth.php`, `validation.php`, … |
| Application strings | `lang/{locale}/…` | `ui.php`, `web/*`, `api/*`, `menu.php`, … |
| Database content | Spatie translatable | JSON fields (`Menu.name`, …) |
| Vue-Workers SPA | `monolith-vue-workers/src/i18n/locales/{en,id,ja,ko,zh-CN,ar,ru}.json` | SPA labels, errors, headings, statuses, and contextual UI copy |

**Never** put app strings in package-managed files (`lang:update` overwrites them).

`localization.md` owns Laravel PHP locale files, API resolution, and parity rules. Sibling [i18n.md](../../../monolith-vue-workers/docs/i18n.md) owns SPA JSON keys, runtime switching, and `useI18n()` context. Cross-repo pointer: [`vue-workers-spa.md` — i18n](../integration/reference-vue-workers-spa.md#i18n-porting-notes). Keep those contracts linked instead of duplicating them.

### Agent: do not dump locale files

Never `Read` an entire `lang/{locale}.json` or SPA `src/i18n/locales/*.json` (thousands of lines each). Find keys with Grep, apply the same targeted patch across all seven locales, then run `tests/Unit/Localization/LocaleParityTest.php` and sibling `npm run check:i18n`. English is the source locale; other locales are patched, not loaded wholesale. Root `lang/*.json` used to carry nested groups duplicating the PHP namespaces for `en`/`id`/`ja`; those groups are **removed** and must not come back — `lang/{locale}/*.php` is the single source for application strings (see [Packages & parity](#packages--parity)).

### Task rule (required)

Any user-facing copy change includes **all** supported locales (`en`, `id`, `ja`, `ko`, `zh_CN`, `ar`, `ru`) in the **same** task/PR.

Every translation must be contextual, not literal. Review copy against its surface (button, heading, toast, validation, status, or explanatory text), audience, domain glossary, and locale-specific grammar/register. Preserve placeholders, plural behavior, and machine-contract text exactly where required; split keys when one source phrase serves different UI contexts. Literal wording that sounds unnatural in Indonesian or Japanese is not accepted.

### Curation flag convention (required)

Every translation this repo curates is flagged **in place**, on the line directly above the key:

```php
    // curated at 2026-09-21T17:05:34+07:00 — id curation wave
    'restore_menu' => 'Pulihkan item menu ini beserta seluruh turunannya yang terhapus?',
```

- Format: `// curated at <ISO8601 with +07:00> — <locale> curation wave`.
- One comment per curated key. Re-curating replaces the existing comment (idempotent — `scripts/apply-locale-translations.php` handles this).
- The comment is a review marker, not documentation: it records that a human/agent read the consuming surface and rewrote the value contextually. Never add it without actually reviewing the string.
- SPA-side JSON cannot carry comments; the equivalent record lives in sibling `monolith-vue-workers/src/i18n/curation-log.json` (see sibling [i18n.md](../../../monolith-vue-workers/docs/i18n.md)).
- Package-managed files (`validation.php`, `auth.php`, `passwords.php`, `actions.php`, `pagination.php`, `http-statuses.php`) **are** curated and flagged like any other file once their wording has been reviewed against the consuming surface. They render directly to users (validation errors, status pages, auth screens), so leaving them at the package default is the same as leaving an application file untranslated. A `lang:update` run overwrites them: after any package upgrade, re-review the file and re-add the flags together with the values. Two keys were deliberately added on top of the pack before this policy existed and are flagged for that reason: `validation.hcaptcha` (`ko`, `ar`, `ru` — missing vs `en`/`id`/`ja`/`zh_CN`) and `api/archivd.blocklist_loaded` (all seven locales — the key was referenced by code with no message anywhere).

Translation workflow:

1. Keep English as the reference/source locale unless key design or context separation requires a source update.
2. Identify surface, audience, domain term, register, placeholders, and plural rules before translating.
3. Review Indonesian and Japanese for natural product language, not word-for-word equivalence.
4. Run locale parity and placeholder tests before review; test locale switching where copy is reactive.
5. Update affected domain documentation when terminology or translation workflow changes.

| Touch | Translate in |
|-------|----------------|
| Remaining PHP `web/*` strings (Discord callback, CSRF, Archivd errors) | `lang/{locale}/web/*` |
| SPA labels | sibling `monolith-vue-workers` JSON locales |
| Query-mode row-action labels / confirms | `lang/{locale}/list_actions.php` (plus reused `ui.*` / `web/*`) |
| API messages / policy / business exceptions | `lang/{locale}/api/*` |
| FormRequest messages | `forms/*` or `__()` in request |
| Translatable DB fields | Spatie + admin input per locale |
| Translatable map locale keys | rewritten in place by `ValidatesTranslatableInput` (SPA alias → canonical code); no locale file |
| Seed sample content | Locale maps in seed data |

Emails follow recipient `users.locale` (`HasLocalePreference` on `User`).

## Locale resolution

`SetLocale` on `web` and `api` (after JWT so `users.locale` is available):

1. `users.locale` (authenticated)
2. `X-SPA-Locale` header (explicit SPA hint — the SPA sends it on every request)
3. `locale` cookie (guests and non-SPA requests)
4. `config('app.locale')`

The header sits above the cookie because the SPA always sends it and it reflects the current choice, whereas the cookie can go stale — on an Arabic direction swap the page hard-reloads, and if that reload aborts the `auth/locale` persist, the cookie keeps the old locale while the header already carries the new one. The authenticated DB preference stays authoritative above both.

The backend stores canonical Laravel codes. SPA `zh-CN` is accepted at API boundaries and normalized to `zh_CN`; `zh_CN` is returned as the canonical backend value. Other supported codes map one-to-one.

Switch: `POST /api/v1/auth/locale` — authenticated users persist to `users.locale`; SPA also stores preference client-side.

Both API (`POST /api/v1/auth/locale`) calls `App\Support\Localization\LocalePreference::apply()` so cookie + user-column persist stay one implementation.

### Translatable payload maps

Spatie-translatable request fields (`menus.name`, `menus.description`, …) accept either a plain string (shorthand for the active locale) or a locale map. The shared trait `App\Support\Localization\Concerns\ValidatesTranslatableInput` owns the map rules:

- **Alias canonicalization** — keys are validated through `LocalePreference::normalize()`, so the SPA alias `zh-CN` is accepted alongside `zh_CN`; the map is then rewritten in place with canonical backend keys, so `validated()` data and persistence never see alias keys. When both keys are present the **last** entry wins the merge, so an SPA edit sent as `zh-CN` overrides a stale `zh_CN` prefill value.
- **All-locale requirement** — a locale map must carry a non-empty value for every supported locale (`validateTranslatableAllLocales`). Plain strings skip the check.
- **Empty map = leave unset** — an empty map on an optional field (`description`) passes validation instead of demanding every locale. The SPA edit form always posts the field, and menus without a description would otherwise fail update with a false "required" on all locales.
- **Unknown keys rejected** — anything `LocalePreference::normalize()` does not resolve fails with a `validation.in` error.

`MenuService::mapAttributes()` canonicalizes again as a safety net for request paths that skip the trait. The SPA mirrors the contract: prefill maps come back backend-keyed (`zh_CN`) and `translatableValue()` resolves them for the row rendered under the SPA code (`zh-CN`), while `normalizeTranslatableMap()` emits backend keys on write. Covered by `tests/Unit/Localization/ValidatesTranslatableInputTest.php`, the `NavigationMenuTest` alias tests, and the SPA `tests/translatableLocaleMap.test.ts`.

### Web ↔ API sync

The SPA sends the active backend locale via `X-SPA-Locale` on API requests. The header carries `zh_CN` when the SPA is using `zh-CN`; other locale codes pass through unchanged. The header wins over the `locale` cookie (see resolution order above) so a stale cookie cannot pin the old locale after an abort-prone direction-swap reload; the cookie remains the fallback for guests and non-SPA requests.

Authenticated clients may also call `POST /api/v1/auth/locale`. Resolution order is db → header → cookie → default (**not** `Accept-Language` alone).

### SPA startup resolution

Vue-Workers resolves its initial locale independently of Laravel middleware:

1. Supported `localStorage.locale` (SPA code; `zh-CN` is canonical in browser storage).
2. First matching supported `navigator.language` / `navigator.languages` alias.
3. `en`.

Only `en.json` ships in the SPA's eager bundle; the other six locales load as dynamic-import chunks on first `setLocale()` (`LAZY_LOCALES` in `monolith-vue-workers/src/i18n/index.ts`), with eager `en` covering missing keys until the chunk resolves. Details: sibling [i18n.md](../../../monolith-vue-workers/docs/i18n.md).

`html[lang]` uses the SPA code (`zh-CN`, not `zh_CN`), while browser `Intl` APIs receive the SPA code. Arabic also sets `html[dir="rtl"]`; all other supported locales set `ltr`. The SPA loads a direction-split global CSS entry (`main-ltr.css` / `main-rtl.css`, resolved before mount from the detected locale); the RTL entry imports `vendor-rtl.css`, a sibling of `vendor.css` that swaps Bootstrap/AdminLTE for their official `.rtl` dist builds (a separate CSS entry, not a Vite `resolveId` alias, because Vite runs CSS `@import` through postcss rather than the JS resolve pipeline). Switching locale across directions (Arabic ↔ any other) therefore triggers a full page reload from `setLocale()` so the other stylesheet loads. Directional icons follow a fixed convention: glyph swap for `bi-arrow-left` back buttons, `scaleX(-1)` for the custom `.ar-breadcrumb__sep` separator, and `isRtl()`-bound classes for list-movement prev/next — AdminLTE's own `.nav-arrow` is mirrored by the vendor RTL build and must NOT get a custom transform (details in sibling [i18n.md](../../../monolith-vue-workers/docs/i18n.md)).

Runtime `setLocale()` persists the SPA choice and updates the HTML `lang` attribute. `window` and `document` access is guarded for SSR and tooling contexts. Authenticated SPA flows may synchronize the choice through `POST /api/v1/auth/locale`, but backend resolution remains user preference → header → cookie → app default. Do not document the two startup paths as one shared precedence chain. On an Arabic direction swap the reload must happen only AFTER `auth/locale` persists — `setLocale()` accepts `reload:false` and `AppNavbar.switchLocale()` calls `reloadForDirection()` last, otherwise the hard reload aborts the persist fetch and the dynamic menu stays on the old locale.

## Folder layout (app-owned)

```
lang/{en,id,ja,ko,zh_CN,ar,ru}/
  ui.php, menu.php, notifications.php, mail.php, list_actions.php
  web/*.php
  api/*.php
  forms/*.php
```

Managed files listed in `config/localization.php` → `managed_files`.

`web/*.php` shrank with the Blade removal — it now holds only strings that surviving **server-side PHP** still resolves: `session`, `identity-connections`, `vtual-vtuber` (Discord callback flash), `payments`, `archivd` (direct-upload errors), `settings` (registry labels), and `vtual-orphans` (orphan archive confirm, reused by query-mode `OrphanCreatorQueryRowActions` — see [list-display-modes.md](list-display-modes.md)). Query-mode row-action labels and mutate confirms that are not already in `ui.php` / `web/*` live in `list_actions.php` (API-emitted; the SPA displays them, it does not re-translate). Browser UI strings live in the SPA's JSON locales, not here. Do not add a `web/*.php` file for a screen; if only the SPA reads it, it belongs in `monolith-vue-workers/src/i18n/locales/*.json`.

## Key conventions

| Context | Example |
|---------|---------|
| Shared UI | `__('ui.actions.save')` |
| Query-mode row actions | `__('list_actions.manage')` — labels/confirms emitted on `actions[]` |
| Server `web` strings | `__('web/session.expired')` — only the handful of files still reached by server endpoints (see below) |
| API domain | `__('api/navigation.created')` |
| Static nav labels | `__('menu.dashboard')` |
| DB menus | `$menu->name` (Spatie) |
| SPA JSON | `t('ui.swal.ok')`, `t('ui.dialog.error_title')` — Swal chrome in `monolith-vue-workers/src/i18n/locales/*.json`; domain confirm bodies stay on their domain keys. Contract: sibling [ui-patterns.md](../../../monolith-vue-workers/docs/ui-patterns.md) |
| SPA chrome (nav) | `t('ui.navigation.load_failed')` — Valkivid3 nav-load fallback used by `useVkNavigation`. Catalog: `ui.error_state.*`, `ui.empty_state.*`, `ui.datatable.*`, `ui.navigation.*` live in the SPA JSON; enumeration in sibling [i18n.md](../../../monolith-vue-workers/docs/i18n.md) |

## Packages & parity

- `php artisan lang:add …` / `lang:update` for managed packs. Managed packs are curated and flagged like application-owned files once reviewed (see the package-managed policy above) — the `zh_CN` wave left 137 flags in `validation.php`, 51 in `actions.php`, 28 in `http-statuses.php`, 3 in `passwords.php`, and 2 in `auth.php`. `lang:update` may rewrite those files and drop both the flags and the reviewed wording; re-review after every package upgrade. Two keys are deliberately added on top of the pack rather than reviewed package values, because the pack does not ship them for every locale: `validation.hcaptcha` (the rule message) was missing in `ko`/`ar`/`ru` and the `attributes.h-captcha-response` label was missing in `ko`/`ar`/`ru` too — with the label absent, an hCaptcha failure renders the raw field name `h-captcha-response` to the user. Both are now present in all seven locales. `api/archivd.blocklist_loaded` sits in an application-owned file but had no message in any locale including `en`, so it was added to all seven locales in one change and flagged in each.

  **You do not have to type `lang:update` for this to happen.** `composer.json` runs it from `post-update-cmd`, so *any* `composer update` — including `composer update --lock`, whose only intended effect is refreshing the lock file's `content-hash` — rewrites `lang/{locale}/**` and `lang/{locale}.json` with upstream vendor text and silently discards every `// curated at` flag and reviewed wording in the process. It leaves ~37 files dirty and the diff reads like a legitimate translation change. Recover with `git checkout -- lang/` (the curated corpus is committed; the vendor rewrite is not worth keeping) and verify with `php artisan test --filter=Locale` before committing. Use `composer update --lock --no-scripts` when only the lock metadata needs refreshing.
- `tests/Unit/Localization/LocaleParityTest.php` checks supported locale directories, application-owned file presence, dot-notation key parity, PHP placeholder parity, and that **no lang file declares the same literal key twice inside one array block**. That last one exists because a duplicate is invisible to every other check: the file parses, the key-parity test sees only the surviving entry, and PHP silently keeps the *last* value — so the rendered string is wrong with nothing to report it. Indentation cannot detect it (a container array and its parent's sibling keys share a depth), so it is tokenized, which also keeps the same key name in two sibling arrays (`steps.1.title` / `steps.2.title`) legal. The curation appliers can create this by accident when a create path inserts a key that already exists, which is exactly how it was found. Managed packs are included in the scan: `validation.php` is where an added-on-top key is most likely to collide with a later `lang:update`. It excludes files listed in `config/localization.php` / package-managed basenames from the key/placeholder comparisons, including `validation.php` from placeholder comparison. Managed files are still required to exist for every supported locale and need a deliberate translation review after `lang:update` or package upgrades.
- Backend coverage inventory is generated with `php scripts/backend-locale-audit.php backend-locale-report.json`. It compares every application-owned PHP file **and each flat `lang/{locale}.json` pack** against `en`, reports missing/extra keys, and reports exact English carryover while excluding identifier-like values and machine fields. The generated report is per file and locale; it is review output, not a translation allowlist. As of the `ko` wave (Sep 2026) the audit reports `missing_keys: 0`, `extra_keys: 0`, `copy_candidates: 0`. That zero is only meaningful because of two fixes made during the `ko` wave:
  - the flat packs were previously invisible. The audit walked `lang/{locale}/` directories only, so `lang/{locale}.json` was never compared — which is how `New` in `lang/ko.json`, `Admin` and `Export :name` in `lang/id.json`, and `All rights reserved.` in `lang/ja.json` survived four waves. Each pack is now audited as a pseudo-file;
  - `isLikelyCopy()` deliberately ignored single-token values to avoid flagging brand names, so a one-word leak in package chrome (`Admin`, `New`) passed. Flat-pack entries are now checked with `$strictSingleToken = true`, because the pack is package chrome where a bare English word is a leak, not a brand.

  The two values that are still byte-identical to `en` are exempted by rule rather than overlooked: `web/vtual-orphans.php` `channels.profile_placeholder_youtube` / `channels.profile_placeholder_twitch` (`UC… / @handle / https://youtube.com/@handle`, `12345678 / login / https://twitch.tv/login`) are input-shape placeholders that must stay as written, and `id` keeps `HTTP polling` / `WebSocket (realtime)` for the notification transport options, where the English term is the accepted Indonesian one.

  `php scripts/locale-report-query.php <locale> [--verbose] [--file=<substr>]` queries the report per locale/file.
- `lang/{locale}.json` note: **all seven** locale JSONs ship flat package scalars only (228 keys). `en.json`/`id.json`/`ja.json` used to carry 16 nested group objects (`actions`, `api`, `ui`, `web`, …) duplicating the PHP namespaces — a historical `laravel-lang` layout. Laravel's `Translator::get()` consults the JSON pack **before** the PHP namespaces for any key without `/`, so those groups **shadowed** `lang/{locale}/*.php`: curated PHP wording never reached `en`/`id`/`ja` users, and a stale JSON value won. The groups are removed, so `lang/{locale}/*.php` is the single source for application strings. **Never re-add a nested group to `lang/{locale}.json`** — `LocaleUsageTest` fails the build if one reappears. Managed `*.json` pack files stay as `lang:update` writes them.
- **The flat pack is in curation scope.** The 228 scalars in `lang/{locale}.json` are `laravel-lang` package chrome (`Delete`, `Save & Close`, `Showing` / `results` / `of` pagination fragments, `Whoops!`, the mail templates), and only **one** of them is consumed by application code (`Reset your password`, from the reset-password notification). They were nevertheless left in the pre-wave machine text until the `ko` wave, which is why they are now curated and logged: the pack is what a user sees on any framework-rendered error or mail surface, and "not ours" is not a reason for it to read like a translation dump. `scripts/apply-pack-translations.php` writes them and records each key in `backend-curation-log.json` (JSON cannot carry an inline `// curated at` comment).
- **Flat-pack mirror policy.** Every flat scalar is classified against `lang/en/**/*.php` first, because Laravel resolves a key without `/` from the JSON pack **before** the PHP namespaces: the same English string must not render two different ways on two surfaces of one product. Of the 228 scalars, **189 are owned** — the English value occurs verbatim in at least one `lang/en/{locale}/*.php` file — and **39 are unowned** package chrome (`Whoops!`, `Go to page :page`, the mail templates) with no PHP counterpart.
  - **Owned scalars mirror their canonical owner.** The owner is chosen by the same precedence the packs use elsewhere: `http-statuses` → `ui` → `actions` → first `web/*` namespace alphabetically, with `OWNER_OVERRIDES` for the three keys where that order picks the wrong sense — `OK` (`ui.swal.ok`, the dialog button, not the HTTP 200 reason phrase), `File` (`web/archivd.type_file`, the content type), and `Connect` (`web/identity-connections.connect`, linking an account, not a generic connection). The pack is set to the owner's value; it is never the other way round, because the owner is the string the consuming surface actually renders.
  - **Unowned scalars are curated independently** against the target language, not against `en`. Before this wave only `ko` (59) and `ru` (57) had been through that, and `ar` had none.
  - **Multi-owner scalars** (29, e.g. `Actions`, `Archive`, `Done`, `Home`, `File`) have the same English value in more than one namespace. The canonical owner is picked from the list above; where the other namespaces differ for the *same* concept, the owner file is fixed instead of the pack (see the per-locale drift lists under [Domain glossary](naming-conventions.md#domain-glossary)). Where the difference is an action verb against a content-type or column noun (`Archive` vs the zip-file type, `Created` as an event vs `created_at` as a timestamp, `Open` as a button vs the error-log status), both are kept and the flat pack takes the action sense, because the pack is package chrome whose consumers are buttons and menu items.
  - `php scripts/locale-flat-pack-parity.php [locale] [--verbose] [--json=<path>]` is the standing guard. It compares each owned scalar against its canonical owner only — comparing against *all* owners would report the 29 multi-owner keys as false positives — and exits non-zero while any locale has drift.
- `LocaleUsageTest` also scans `app/`, `routes/`, and `config/` for literal `__(…)`, `trans(…)`, and `trans_choice(…)` keys (plus `'message_key' => '…'` literals) and asserts each resolves in `lang/en`. A key that exists nowhere is now a test failure instead of a raw key rendered to the user.
- Translation waves run from the audit/report worklists with a per-locale/per-namespace batching helper (`scripts/locale-report-query.php` backend, `scripts/split-worklist.mjs` + `scripts/dup-query.mjs` SPA-side). No machine-translation cache is used; prior Google-Translate draft artifacts were rejected and deleted. All translations are produced contextually against the English source plus the consuming code surface, and follow the [Domain glossary](naming-conventions.md#domain-glossary).
- **Coverage status (Sep 2026).** `id`, `ja`, `zh_CN`, `ar`, `ko`, and `ru` received the full contextual wave on **both** sides, and the closing pass brought all three guards to zero. Backend curated flags: `id` 1276, `ja` 730, `zh_CN` 774, `ar` 264, `ko` 470, `ru` 819 (`en` 5 — the source comments only, never a translation). For `ar` that is 31 of the 41 `lang/ar/**/*.php` files, for `ko` 37 of 41, `ja` 35 of 41, `zh_CN` 36 of 41, `id` 39 of 41, and for `ru` 29 of 41; the remainder were reviewed and needed no change, which is verifiable rather than assumed, because every one of their values already differs from `en` and none is blank. SPA logged keys: `id` 1090, `ja` 1071, `zh-CN` 1429, `ar` 1870, `ko` 1484, `ru` 835, `en` 54. Flat pack curated keys in `backend-curation-log.json`: `ru` 117, `zh_CN` 114, `ko` 101, `id` 94, `ar` 87, `ja` 78 of 228. `zh_CN`, `ar`, `ko`, and `ru` additionally fixed the pre-existing term drift listed under [Domain glossary](naming-conventions.md#domain-glossary) — the `ar` wave had the most severe case, `slug` split four ways including `سبيكة` ("metal ingot") in 19 SPA keys, and the `ru` wave had the largest set, recorded in the `ru` drift list below. The closing pass added the `id`/`ja`/`zh_CN` drift lists, corrected two stale glossary rows and two stale hand-offs, and is what took the cross-repo pack guard and the flat-pack guard to 0 for every locale. Run the same recipe per locale — dump pairs, review against surface and glossary, apply with flags, run the guards — before treating it as done.

  **Read these counts as a snapshot, never as a target.** The backend numbers rose in milestone 57, when the same pass curated two new API-emitted surfaces in every locale: the claims-hub row-action vocabulary in `lang/*/list_actions.php` (`verify_claim`, `accept_anyway`, plus `confirm.cancel_claim_title` / `confirm.cancel_claim` / `confirm.accept_anyway_claim_title` / `confirm.accept_anyway_claim` for `/apps/vtual/claims`) and the upstream retry/quota ops log lines in `lang/*/api/vtual.php`. Both are API-emitted: the SPA renders the strings the API sends, it does not re-translate them, so they are curated here and stay in step with `VtualClaimQueryRowActions` and `PlatformHttp`. The SPA numbers moved **down** for the same period, and that is not lost curation: the 2026-09-24 and 2026-09-25 orphan sweeps deleted unreachable `web.vtual*` keys and, in the same change, the curation-log entries that pointed at them (an entry whose key no longer exists fails `check-curation-log.mjs`). A falling SPA count is the sweep working; a falling backend count would be a defect.

  A locale is only closed when **all three** cross-repo guards report 0 for it: `locale-pack-parity.php` (framework packs), `locale-flat-pack-parity.php` (flat pack against its PHP owners), and `locale-glossary-scan.php` (settled terms). "Wave complete" means the review happened; "locale closed" means the guards agree, and only the guards can say that.

- **Cross-repo pack parity is a guard, not a convention.** The SPA `validation.*` and `http-statuses.*` namespaces are copies of the backend `lang/{locale}/*.php` packs, and a validation error can be rendered from either side depending on where the request was rejected. Both copies must therefore carry the same wording for the same key, with only the placeholder sigil differing (`:attribute` in PHP, `{attribute}` in vue-i18n). The `ar` wave ended with 8 silent divergences — `validation.extensions` phrased two different ways, `attributes.product_id` and `attributes.product_uid` collapsed onto the same Arabic string (a real collision: the two English keys are *product ID* and *product UID*), and five `attributes.*` labels definite in one copy and indefinite in the other. Nothing caught them, because each side was internally consistent and every per-locale guard is per-repo.

  `php scripts/locale-pack-parity.php [locale] [--verbose] [--json=<path>]` is now the standing guard. It compares the mirrored packs on both sides, normalizing `:x` to `{x}` (case-insensitively, because Laravel substitutes both `:attribute` and `:Attribute`), `{'@'}` to `@`, and HTML entities (`&laquo;`/`&raquo;`) to the characters the SPA ships, then reports divergent keys per pack and exits non-zero while any checked locale is above 0. Current state:

  | locale | divergent | cause |
  |--------|-----------|-------|
  | `ko` | 0 | closed by the `ko` wave |
  | `ar` | 0 | closed by the `ar` wave, plus one leftover repaired in the `ko` wave (see below) |
  | `id` | 0 | closed by the closing pass: `lang/id/validation.php` had **0** curated flags (raw laravel-lang text) against a curated SPA copy, and the 26 `http-statuses` divergences were the reverse (backend curated, SPA not mirrored). Both packs were settled on one wording per key and mirrored |
  | `ja` | 0 | closed by the closing pass: same shape as `id` — `validation.php` uncurated, `http-statuses` curated on the backend only |
  | `ru` | 0 | closed by the `ru` wave: one wording settled per framework pack (`validation`, `http-statuses`, `auth`, `passwords`) and mirrored to the SPA, 179+48+3+4 divergences to 0 |
  | `zh_CN` | 0 | closed by the closing pass: **both** copies were curated (137 and 150 flags) and still disagreed, so this was real per-key drift, not a missing pass — 150 `validation` keys, 19 `http-statuses`, 3 `auth`, 3 `passwords` settled key by key |

  The lesson is that curating one side of a mirrored pack is not curating the pack — and that curating both sides is not either, because two independently curated copies drift. The only thing that closes a pack is settling one wording per key and mirroring it, which is what the closing pass did for `id`, `ja`, and `zh_CN`.

  The `ar` row read `0` for a whole session while the guard actually reported `1`: `validation.attributes.h-captcha-response` was `كلمة التحقق` on the backend and `الكابتشا` in the SPA. It surfaced only when the `ko` wave added the same missing key to `ar` and re-ran the guard. Two things follow. A guard's *table entry* is a snapshot, not a live value — re-run it, do not quote it. And when a fix aligns one side of a mirrored pack, align the other side to it in the same change: the `ar` backend copy now reads the SPA's `الكابتشا`, which matches the definite-article style of every other `ar` attribute label (`الاسم`, `البريد الإلكتروني`).

  The mirror set is deliberate and short — `validation`, `http-statuses`, `auth`, `passwords`, `pagination`. `actions.*` is **not** in it: the SPA `actions.*` block is the SPA's own shared action vocabulary, curated against its own surfaces, and it legitimately differs from the `actions.php` pack (`ko` noun forms 追加/취소 vs the pack's -하기 forms 추가하기/취소하기, `ja` 閲覧 in the SPA's generic `actions.view` vs 表示 in the pack, where 表示 is also what `ui`/`web/archivd` use). Comparing it would report the intended design as a defect, which is how the `ko` wave briefly regressed the SPA button register by syncing it.
- Vue-Workers `npm run check:i18n` chains three checks: `scripts/check-i18n.mjs` (JSON key parity across all seven SPA locales, Vue interpolation tokens, malformed braces, exact English duplicates, hardcoded fallback-copy patterns under `src/`), `scripts/check-i18n-usage.mjs` (every literal `t()`/`tm()`/`te()`/`$t()` key under `src/**/*.{ts,vue}` must exist in `en.json` — a key present in no locale is a hard failure, since `fallbackLocale: en` cannot rescue it), and `scripts/check-curation-log.mjs` (curation-log key existence, ISO8601 timestamps, no stale entries). Duplicate-English values fail unless `isDuplicateEnglishAllowed(key, locale)` returns true; the rule lives in `scripts/lib/duplicate-english-allowlist.mjs` and is **locale-scoped**, because a loanword is a property of one language, not of a key — a flat key-only list is how `ko.ui.copyright` and `zh-CN.web.vtual.public.title` stayed English while both were allowlisted. The module holds two lists: `globalDuplicateEnglishKeys` for brands and machine-contract text (format placeholders, probe method names, `sha256`, `{count} / 2000` hints, `YYYY-MM-DD`, `LIVE`/`VOD`, `QRIS`, `Discord`, `vTual`, `Microstock`, `Archivd`), and `localeDuplicateEnglishKeys` for the loanwords whose accepted term in that locale **is** the English word. Today only `id` needs the second list (bahasa gaul IT: `Status`/`Filter`/`Model`/`Margin`/`Label`/`Avatar`/`Channel`/`Platform`/`Gateway`/`Slug`/`Navbar`/`Sidebar`/`Checkout`/`Upgrade`/`Downgrade`/`Addon`/`Host`/`Marketplace`/`Token`/`API key`/`Access token`/`Refresh token`/`Client secret`/`Email`/`Bank`/`Audio`/`Video`/`Thumbnail`/`Subtotal`/`Total`/`Settlement`/`Folder`); `ja`, `ko`, `zh-CN`, `ar`, and `ru` have an empty list, so any English carryover in them is a hard failure. Prefer translating when a natural native term exists; add a key to a list only when the carryover is genuinely correct for that language. Both scripts import the module, so the checker and the report can no longer disagree. `node scripts/i18n-duplicate-report.mjs` writes `i18n-duplicate-report.json` with locale/namespace/key-level counts, classifications, placeholder tokens, actionable totals, and stale-allowlist detection, and prints a warning when `actionable_pairs` is non-zero. The report is generated inventory, not permission to retain untranslated copy — it is a manual review artifact like the backend audit, **not** a CI step (the SPA `.github/workflows/ci.yml` runs `type-check`, `check:i18n`, `test`, `build-only`).

## Curation tooling

Translation waves use the scripts below plus the guards above. They are review helpers, not generators: no machine-translation cache exists, and prior Google-Translate draft artifacts were rejected and deleted.

Every guard in this section is **permanent and versioned** — they live in `scripts/` (not in `.tmp-i18n/`) and are wired into the test suite by `tests/Unit/Localization/LocaleGuardTest.php`, which shells out to each one and asserts exit 0. A guard that cannot see the sibling `monolith-vue-workers` checkout **exits 1** rather than reporting `0 divergent`; `locale_scan_spa_present()` in `scripts/lib/locale-scan-support.php` is the single definition of that question (checkout present **and** reference pack readable, so a partial checkout is not mistaken for a usable one), and `LocaleGuardTest::test_sibling_repo_detection_matches_the_guard_helper` asserts the helper and the test agree about the environment. When the sibling is absent the four cross-repo guards are reported as **skipped** rather than green — see [CI coverage vs manual runs](#ci-coverage-vs-manual-runs). Shared plumbing (path resolution, flattening, `--json` output, locale selection) is in `scripts/lib/locale-scan-support.php`; the prose allowlist is in `scripts/lib/locale-latin-allowlist.php`.

| Script | Purpose |
|--------|---------|
| `php scripts/locale-pair-dump.php <locale> [--file=<substr>] [--ns=<prefix>]` | Read-only `namespace \t key \t EN \t <locale>` dump for review batches. Complements `locale-report-query.php` (which queries the generated audit report). |
| `php scripts/apply-locale-translations.php <locale> <file.php> <map.json> --note="<locale> curation wave"` | Patches dotted keys in `lang/<locale>/<file>.php` by nesting depth: replaces the value, inserts/replaces the `// curated at` comment above the key, preserves formatting, indentation, and placeholders. Reports `replaced=/created=/failed=`. `--anchor=<key>` creates a key after an existing leaf; `--literal-key` is required to create a dotted key as a literal name (settings registry labels like `microstock.ai.vision.connection`) instead of nesting it. **Creating a key that already exists silently produces a duplicate** — PHP keeps the last one, and `LocaleParityTest::test_no_lang_file_declares_a_literal_key_twice` is what catches it, so check the reported `created=` list against what you expected rather than trusting a clean run. Deeply nested array-element keys sometimes need a direct edit — that is expected, not a bug. |
| `php scripts/backend-locale-audit.php backend-locale-report.json` | Regenerates the per-file/per-locale coverage inventory consumed by `locale-report-query.php`. Covers `lang/{locale}.json` as a pseudo-file and applies the strict single-token rule to it. |
| `php scripts/apply-pack-translations.php <locale> <map.json> --note="…"` | The flat-pack twin of `apply-locale-translations.php`. Rewrites values only for keys that already exist in `lang/<locale>.json` (adding a key is a `lang:update` concern), line-surgically, and records each key in `backend-curation-log.json` with its timestamp, surface, and source. Refuses to write if any key is unknown or the result is not valid JSON. |
| `php scripts/locale-prose-scan.php [locale] [--verbose] [--json=<path>]` | The guard for English left inside translated prose — the class the parity gates cannot see, because the value differs from `en` and is therefore not a duplicate. Two modes, because locales fail differently: `script` for `ja`/`ko`/`zh_CN`/`ar`/`ru` (any Latin word of 3+ letters inside a value that also contains target-script text is a hit, unless it is a machine token or an accepted loanword) and `english` for `id` (a Latin scan is pure noise there, so only the English domain terms whose settled `id` rendering is a *different* word are reported — `management`→`manajemen`, `role`→`peran`, `creator`→`kreator`; a term the glossary keeps in English, such as `workspace` or `checkout`, is accepted and not watched). Both modes strip non-copy content first — placeholders, snake_case/camelCase identifiers, quoted enum literals, `key=value` pairs, URLs, CLI flags — which is what makes it usable as a gate rather than a noise generator. The allowlist is `scripts/lib/locale-latin-allowlist.php`: `machine_patterns` (stripping regexes), `machine` (machine/brand tokens, phrases allowed), `glossary` (the settled renderings the `id` mode watches), and `accepted` (per-locale loanwords). Exits non-zero on any hit. |
| `php scripts/locale-rawkey-scan.php [locale] [--verbose]` | Detects a translation *value* that is itself a dotted or slashed key path — a raw key rendered to the user because the lookup missed and something fell through to the key. Zero tolerance: any value that has key shape (lowercase, separator-joined, two or more segments) and does not resolve against the locale's own key set is a hit. Shape alone is not enough to report, because `youtube.com` and `Routing_Price.yaml` have the same shape; a final segment that is a known domain or file extension is rejected. Exits non-zero on any hit. |
| `php scripts/locale-dynamic-key-check.php [--verbose]` | Enumerates the keys that are assembled at runtime and are therefore invisible to a static `__()` scan, then asserts each resolves in all seven locales: `api/activity-log.events.*`, `NotificationType::messageKey()`, `api/payments.*`, `api/error-log.statuses.*`, and the sentence keys used as mail/flat-pack keys. `LocaleUsageTest` covers literal call sites; this covers the ones built from a constant or an enum. Exits non-zero when a runtime-assembled key resolves in no locale. It also fails on a **new** dynamic call site that the enumeration does not know about, so a new `__($variable)` cannot quietly escape the check. |
| `php scripts/locale-pack-parity.php [locale] [--verbose] [--json=<path>]` | Cross-repo mirror guard: diffs the backend framework packs against the SPA namespaces for `validation`, `http-statuses`, `auth`, `passwords`, and `pagination`, normalizing the placeholder sigil, `{'@'}`, and HTML entities. Exits non-zero while any checked locale diverges; the JSON report is opt-in. |
| `php scripts/locale-flat-pack-parity.php [locale] [--verbose] [--json=<path>]` | Flat-pack guard: classifies every scalar of `lang/{locale}.json` as owned (the English value occurs in `lang/en/**/*.php`) or unowned, picks each owned scalar's canonical owner (`http-statuses` → `ui` → `actions` → first `web/*` namespace, with `OWNER_OVERRIDES` for `OK`, `File`, `Connect`), and reports every scalar whose value differs from that owner. Compares against the canonical owner only, so the 29 multi-owner scalars do not read as false positives. Exits non-zero while any locale diverges. |
| `php scripts/locale-glossary-scan.php [locale] [--verbose]` | Enforces the settled terms in [naming-conventions.md](naming-conventions.md) instead of only documenting them. It carries a `FORBIDDEN` and a `MIN_OCCURRENCES` map for **all six** non-`en` locales (`id`, `ja`, `ko`, `zh_CN`, `ar`, `ru`); the `id`/`ja`/`zh_CN`/`ar` maps were added by the closing pass, each entry taken from a measured count rather than guessed. Per locale it scans the backend `lang/{locale}/**/*.php`, `lang/{locale}.json`, and the SPA locale file for two classes: a **forbidden** substring that a better term replaced everywhere (precise, zero false positives), and a **floor** on how often a settled term appears per side, for the collapse case a ban cannot express — `ko`'s `checkout`, where the rejected rendering `결제` is a correct word in general and only wrong inside checkout copy. Exits non-zero on any hit. A floor is a tripwire, not a completeness proof: it fails when a wave reverts many keys at once, which is the realistic regression, and it only ever needs raising, since added keys raise the real count. Two floors deserve a note because they look wrong otherwise: `ru`'s `истёк`/`Истёк` are floored **separately** (`substr_count` is case-sensitive, and a single floor of 11 silently summed both casings and could never be met), and `ar`'s `تحقق` has no shadda because that is what the corpus writes. |

Guard rails, run for every batch: `php artisan test --filter=Locale` (parity, usage, and every guard in the table above) and `php scripts/backend-locale-audit.php backend-locale-report.json` when you want the refreshed coverage inventory. The individual guards are worth running directly when you want their per-key findings: `php scripts/locale-glossary-scan.php`, and — after any pack, `actions.*`, or flat-pack change — `php scripts/locale-pack-parity.php` and `php scripts/locale-flat-pack-parity.php`.

`php artisan test --filter=Locale` is the gate. It runs `LocaleParityTest`, `LocaleUsageTest`, and `LocaleGuardTest`, and `LocaleGuardTest` shells out to every guard in the table above and asserts exit 0 — one command, one implementation, no logic duplicated into the test suite. Reach for the individual scripts when you want the readable per-key findings instead of an aggregated PHPUnit failure.

### CI coverage vs manual runs

The backend workflow is `.github/workflows/ci.yml`, and its `locale` job runs **only the checks that need one repo**, so it carries no credentials — no cross-repo token, no sibling checkout. The workflow's second job, `backend`, runs the whole test suite for the same reason: one repo, no services. It reruns these locale tests as part of `php artisan test`; the narrow `locale` job stays because its direct guard runs print the offending key and locale instead of burying them in an aggregated PHPUnit failure. Which defect classes the `locale` job covers, and which four guards need the sibling checkout:

| Class of defect | Guard | Needs sibling repo? | Gated in CI |
|---|---|---|---|
| Key/placeholder parity across locales, duplicate literal keys | `LocaleParityTest` | no | yes |
| A key referenced from code that no locale defines | `LocaleUsageTest` | no | yes |
| Flat pack `lang/{locale}.json` drifted from its PHP owner | `locale-flat-pack-parity.php` | no | yes |
| A runtime-assembled key that resolves in no locale | `locale-dynamic-key-check.php` | no | yes |
| English left inside non-English prose | `locale-prose-scan.php` | **yes** | no |
| A value that is itself a dotted key (raw key shown to the user) | `locale-rawkey-scan.php` | **yes** | no |
| Backend framework packs vs the SPA packs | `locale-pack-parity.php` | **yes** | no |
| A settled glossary term reverted, or its occurrence floor collapsing | `locale-glossary-scan.php` | **yes** | no |

Four of the eight are gated in CI; the other four run from a checkout that has `monolith-vue-workers` as a sibling. `LocaleGuardTest` reports those four as **skipped** when the sibling is absent (after asserting the guard fails loudly with `exit 1` and `cannot see the SPA repo`), so a green CI run cannot be mistaken for "all six guards ran".

**The occurrence floors are calibrated to the corpus, so a key deletion moves them.** They exist because the rejected rendering of a collapse (`ko` `결제` for *checkout*, `ru` `оплата` for *checkout*) is a correct word elsewhere and cannot be banned as a literal. The floor only catches a mass revert, which assumes the corpus grows. Deleting unreachable keys shrinks it: the 2026-09-24 sweep of 344 dead `web.vtual*` SPA keys dropped every `spa` floor below its recorded value and produced 40 false `reverted?` findings. When you delete keys, run `php scripts/locale-glossary-scan.php --verbose`, confirm the term is still present and the drop is explained by the deleted keys (not by rewording), and set the floor to the measured count in the same change. Do not leave a floor above the real count — a permanently red gate is worse than no gate.

The job's runner environment is part of this contract, not incidental: it pins PHP 8.4, sets `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` (so `composer install` can boot `SettingsServiceProvider` without a MySQL service), creates an empty `.env`, and widens `COLUMNS`. Changing any of those breaks the job in a way that reads like a corpus failure. The `backend` job reuses that same `env:` block through a YAML alias, so the two cannot drift. Per-step reasoning for both: [reference-tooling.md — CI (GitHub Actions)](reference-tooling.md#ci-github-actions).

An earlier version of this workflow did check out the sibling and run all six. It never worked: the sibling is private, `GITHUB_TOKEN` is scoped to the repo running the workflow, and the checkout step failed `Not Found` before a single test executed — a red `main` caused by the gate itself, not by the corpus. Reading the sibling needs a fine-grained PAT, and the four guards still would not cover the SPA-only case (see below). The workflow was narrowed instead of patched.

### PR & merge process

1. Run the gates locally, from a checkout that has `monolith-vue-workers` as a sibling: `php artisan test --filter=Locale` (covers all six guards) and `npm run check:i18n` in the SPA.
2. Push and open the pull request.
3. Wait for the backend `locale` and `backend` jobs, and the SPA `check` job, to go green.
4. Merge.

Step 3 is a convention, not an enforced rule. These repos are private and the org is on the GitHub **free** plan, so branch protection and required status checks are unavailable — `gh api repos/silverspoonmedia/monolith/branches/main/protection` answers `403 Upgrade to GitHub Pro or make this repository public`. Nothing on GitHub blocks a merge with a red run; merging anyway leaves `main` red, which is what happened to PR #77 and #10 when the cross-repo workflow above was in place. The gate protects the corpus only when someone looks at it.

The `backend` job is the slow one (whole suite, ~9 min on the dev machine and longer on a 2-vCPU runner), and it is the only thing that runs `tests/Domain/**` on a push. A PR that skips waiting for it is a PR whose domain regressions nobody sees until the next local run. A green run reports `2461 tests, 2460 passed, 1 skipped, 1 risky` — the skip is the opt-in OCI KMS live test and the risky is a pre-existing stdout report; only `failed` must be 0. Details: [reference-tooling.md — CI](reference-tooling.md#ci-github-actions).

One gap follows from the coverage table and is worth stating plainly: **a pull request that only touches SPA locale files is not gated by the cross-repo guards.** `npm run check:i18n` is per-repo and cannot see the backend, and the backend workflow triggers on backend changes. If a batch changes a mirrored pack (`validation`, `http-statuses`, `auth`, `passwords`, `pagination`) or a settled glossary term, run `php artisan test --filter=Locale` from the backend checkout before merging — that is the only thing that reads both halves.

When a scan's output has to be read rather than asserted on, write it to a UTF-8 file and read the file — the PowerShell console mangles non-Latin scripts, so printing to the console silently hides the very defects being searched for. `--json=<path>` exists on every guard for this reason.

The parity guards compare keys, placeholders, and exact-English duplicates. They do **not** catch leftover Latin words inside an otherwise-translated sentence, or a term that drifted away from the glossary while still differing from English. Both classes shipped unnoticed until the `ar` wave (`web.vtual.claims.user_create_note` was half English; `slug` had four Arabic spellings). `locale-glossary-scan.php` closes the second class for the terms it knows; `locale-prose-scan.php` closes the first, and it is a **permanent, versioned guard** rather than the throwaway per-wave scan this section used to describe — the earlier version lived in `.tmp-i18n/`, was gitignored, and was rebuilt every wave with the same bugs (it did not strip placeholders in `english` mode, so `:role` and `:management` read as English prose, and it counted from stale artifacts, so it kept reporting `management` in `lang/id/api/vtual.php` long after that file read `manajemen`). Anything generalizable that a wave discovers now goes into `locale-prose-scan.php` and `scripts/lib/locale-latin-allowlist.php` instead of into a new scratch script.

When a scan's output has to be read rather than asserted on, write it to a UTF-8 file and read the file — the PowerShell console mangles non-Latin scripts, so printing to the console silently hides the very defects being searched for. `--json=<path>` exists on every guard for this reason.

### Readability checklist

The guards prove *coverage* — every locale has the key, the value is not English, the glossary term is present. They cannot prove the string is *readable*, which is the third half of the definition of done and the one that has caused the most rework. Before a translation is done, read the string as a native speaker of the target locale, **on the surface it renders on**:

- **Register.** `id` uses neutral formal *Anda*; `ja` uses です/ます; `ko` uses 해요체 for instructions but 합쇼체 for declarative statements; `zh_CN` uses 您; `ar` uses MSA; `ru` uses lowercase *вы*. A string that mixes registers inside one screen reads as machine output even when every word is correct.
- **Surface.** A button label, a table column header, a toast, a validation message, a status badge, an empty state, and a mail body are seven different registers. If two surfaces need different wording, they need two keys — the guards will happily pass one key rendering in both, and it will read wrong in one of them.
- **Calque.** Word-for-word rendering of an English idiom is the most common defect and the least visible to a non-speaker: `id` `Dilarang` for `Forbidden` where the product says `Terlarang`; `ko` `가시성` for `visibility` where Korean product UI says `공개 범위`; `zh_CN` `子弹` ("bullet") for a badge `标识`.
- **False friend.** `ja` `クレーム` is "complaint", not "claim" (the product means `申請`); a shared kanji or cognate is not a shared meaning.
- **Leftover English.** `management` in an `id` sentence, `UI` inside an `ar` sentence, `Test` where `zh_CN` has `测试`. The scan catches the ones in its watch list; it is a net, not a proof.
- **Filler that the source does not have.** `ru` `Пожалуйста` padding a UI label, or an `id` sentence that explains what the button already says. Translation is not an opportunity to add courtesy the English does not carry.
- **Placeholders in the right places.** A placeholder moved to a different clause, or dropped, is a runtime bug the parity check only catches when the counts differ.

If the string still reads like a translation of the English, it is not finished, regardless of what the guards report.

## Settings labels ↔ SettingRegistry

`lang/{locale}/web/settings.php` is not free-form copy: its `fields.*` and `hints.*` sub-trees must cover every definition in `App\…\SettingRegistry::definitions()` (148 definitions as of the Sep 2026 wave). A definition without a matching `fields.<dotted-key>` renders as a raw key in the SPA settings form. When adding or renaming a registry definition, patch `web/settings.php` for **all seven** locales in the same change, and remember that dotted definition keys are **literal** keys in the PHP file (quoted, not nested arrays) — the SPA's `fieldLabel()` does a dotted lookup against the flat-ish structure.

## Number & date display (not lang files)

User-facing **numbers and datetimes** (money separators, absolute dates, relative "2 hours ago") follow `app()->getLocale()` via `App\Support\Presentation\{Date,Money,Byte}Presenter` at the API Resource layer. They are **not** translated through `lang/{locale}/*` string keys. API `*_formatted` and `*_human` companions remain authoritative for every API consumer.

SPA-native values that are not already formatted by the API use `Intl.NumberFormat(locale.value)` and `Intl.DateTimeFormat(locale.value, …)` with the active Vue locale. Do not call unqualified `toLocaleString()` for user-facing values.

Clients must not reformat API values — consume Resource companions (`*_formatted`, `*_human`). Contract: [platform/reference-crud.md — API Resource presentation](reference-crud.md#api-resource-presentation-dual-fields).

### Digits are locale-dependent — never assert on the rendered glyphs

`NumberFormatter` renders digits in the locale's own numbering system, and which one depends on the ICU build, not on the code. On the GitHub runner `ar` produces Arabic-Indic digits (`USD ١٬٢٣٤٫٥٦`); the local Windows toolchain produced ASCII (`USD 1,234.56`) for the same input. A test that asserts a formatted amount contains `'1'`, `'234'`, or `'56'` is therefore asserting the runner's ICU version, and it failed the first real CI run of the localization workflow (`MoneyPresenterTest::test_each_supported_locale_returns_a_formatted_amount`).

Normalize before asserting, then assert on the value:

```php
$ascii = preg_replace_callback(
    '/\p{Nd}/u',
    static fn (array $m): string => (string) \IntlChar::digit($m[0]),
    $formatted
);
```

The same applies to `DatePresenter` output and to any SPA-side `Intl` assertion: assert the shape and the value, never the digits or the separator glyphs. `MoneyPresenter`'s `id` separators (`1.234,56`) are a documented product decision, not an ICU accident — those assertions stay.

## Related

- [localization.md](localization.md) — brief
- [naming-conventions.md](naming-conventions.md) — settled terms and drift logs
- [web/blade-admin.md](../web/blade-admin.md)
- sibling [i18n.md](../../../monolith-vue-workers/docs/i18n.md) — SPA JSON locales, runtime switching, fallback translation, `useI18n()` context, Sweetalert2 chrome keys (`ui.swal.*`, `ui.dialog.*`)
- [integration/vue-workers-spa.md](../integration/vue-workers-spa.md) — cross-repo contract pointer
- [domains/navigation/README.md](../domains/navigation/README.md) — Spatie menu fields
