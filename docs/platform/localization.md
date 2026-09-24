# Localization (multi-language)

Cookie- or user-preference-driven locale for API and DB content. **No route prefix** (`/en/...`). The sibling Vue-Workers SPA has its own JSON locale runtime; see sibling [i18n.md](../../../monolith-vue-workers/docs/i18n.md). Cross-repo pointer: [vue-workers-spa.md](../integration/vue-workers-spa.md#i18n-porting-notes).

**Canonical supported locales:** backend codes `en`, `id`, `ja`, `ko`, `zh_CN`, `ar`, `ru` (SPA/browser: `zh-CN` ↔ backend `zh_CN`). All other docs link here instead of restating the list.

## Architecture

| Layer | Tool | Use for |
|-------|------|---------|
| Package defaults | `laravel-lang/common` | `auth.php`, `validation.php`, … |
| Application strings | `lang/{locale}/…` | `ui.php`, `web/*`, `api/*`, `menu.php`, … |
| Database content | Spatie translatable | JSON fields (`Menu.name`, …) |
| Vue-Workers SPA | `monolith-vue-workers/src/i18n/locales/{en,id,ja,ko,zh-CN,ar,ru}.json` | SPA labels, errors, headings, statuses, and contextual UI copy |

**Never** put app strings in package-managed files (`lang:update` overwrites them).

`localization.md` owns Laravel PHP locale files, API resolution, and parity rules. Sibling [i18n.md](../../../monolith-vue-workers/docs/i18n.md) owns SPA JSON keys, runtime switching, and `useI18n()` context. Cross-repo pointer: [`vue-workers-spa.md` — i18n](../integration/vue-workers-spa.md#i18n-porting-notes). Keep those contracts linked instead of duplicating them.

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
  - **Multi-owner scalars** (29, e.g. `Actions`, `Archive`, `Done`, `Home`, `File`) have the same English value in more than one namespace. The canonical owner is picked from the list above; where the other namespaces differ for the *same* concept, the owner file is fixed instead of the pack (see the per-locale drift lists under [Domain glossary](#domain-glossary)). Where the difference is an action verb against a content-type or column noun (`Archive` vs the zip-file type, `Created` as an event vs `created_at` as a timestamp, `Open` as a button vs the error-log status), both are kept and the flat pack takes the action sense, because the pack is package chrome whose consumers are buttons and menu items.
  - `php scripts/locale-flat-pack-parity.php [locale] [--verbose] [--json=<path>]` is the standing guard. It compares each owned scalar against its canonical owner only — comparing against *all* owners would report the 29 multi-owner keys as false positives — and exits non-zero while any locale has drift.
- `LocaleUsageTest` also scans `app/`, `routes/`, and `config/` for literal `__(…)`, `trans(…)`, and `trans_choice(…)` keys (plus `'message_key' => '…'` literals) and asserts each resolves in `lang/en`. A key that exists nowhere is now a test failure instead of a raw key rendered to the user.
- Translation waves run from the audit/report worklists with a per-locale/per-namespace batching helper (`scripts/locale-report-query.php` backend, `scripts/split-worklist.mjs` + `scripts/dup-query.mjs` SPA-side). No machine-translation cache is used; prior Google-Translate draft artifacts were rejected and deleted. All translations are produced contextually against the English source plus the consuming code surface, and follow the [Domain glossary](#domain-glossary) below.
- **Coverage status (Sep 2026).** `id`, `ja`, `zh_CN`, `ar`, `ko`, and `ru` received the full contextual wave on **both** sides, and the closing pass brought all three guards to zero. Backend curated flags: `id` 1272, `ja` 726, `zh_CN` 772, `ar` 262, `ko` 466, `ru` 815. For `ar` that is 31 of the 41 `lang/ar/**/*.php` files, for `ko` 37 of 41, and for `ru` 29 of 41; the remainder were reviewed and needed no change, which is verifiable rather than assumed, because every one of their values already differs from `en` and none is blank. SPA logged keys: `id` 1219, `ja` 1156, `zh-CN` 1599, `ar` 1987, `ko` 1503, `ru` 884. Flat pack curated keys in `backend-curation-log.json`: `ru` 117, `zh_CN` 114, `ko` 101, `id` 94, `ar` 87, `ja` 78 of 228. `zh_CN`, `ar`, `ko`, and `ru` additionally fixed the pre-existing term drift listed under [Domain glossary](#domain-glossary) — the `ar` wave had the most severe case, `slug` split four ways including `سبيكة` ("metal ingot") in 19 SPA keys, and the `ru` wave had the largest set, recorded in the `ru` drift list below. The closing pass added the `id`/`ja`/`zh_CN` drift lists, corrected two stale glossary rows and two stale hand-offs, and is what took the cross-repo pack guard and the flat-pack guard to 0 for every locale. Run the same recipe per locale — dump pairs, review against surface and glossary, apply with flags, run the guards — before treating it as done.

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
| `php scripts/locale-glossary-scan.php [locale] [--verbose]` | Enforces the settled terms below instead of only documenting them. It carries a `FORBIDDEN` and a `MIN_OCCURRENCES` map for **all six** non-`en` locales (`id`, `ja`, `ko`, `zh_CN`, `ar`, `ru`); the `id`/`ja`/`zh_CN`/`ar` maps were added by the closing pass, each entry taken from a measured count rather than guessed. Per locale it scans the backend `lang/{locale}/**/*.php`, `lang/{locale}.json`, and the SPA locale file for two classes: a **forbidden** substring that a better term replaced everywhere (precise, zero false positives), and a **floor** on how often a settled term appears per side, for the collapse case a ban cannot express — `ko`'s `checkout`, where the rejected rendering `결제` is a correct word in general and only wrong inside checkout copy. Exits non-zero on any hit. A floor is a tripwire, not a completeness proof: it fails when a wave reverts many keys at once, which is the realistic regression, and it only ever needs raising, since added keys raise the real count. Two floors deserve a note because they look wrong otherwise: `ru`'s `истёк`/`Истёк` are floored **separately** (`substr_count` is case-sensitive, and a single floor of 11 silently summed both casings and could never be met), and `ar`'s `تحقق` has no shadda because that is what the corpus writes. |

Guard rails, run for every batch: `php artisan test --filter=Locale` (parity, usage, and every guard in the table above) and `php scripts/backend-locale-audit.php backend-locale-report.json` when you want the refreshed coverage inventory. The individual guards are worth running directly when you want their per-key findings: `php scripts/locale-glossary-scan.php`, and — after any pack, `actions.*`, or flat-pack change — `php scripts/locale-pack-parity.php` and `php scripts/locale-flat-pack-parity.php`.

`php artisan test --filter=Locale` is the gate. It runs `LocaleParityTest`, `LocaleUsageTest`, and `LocaleGuardTest`, and `LocaleGuardTest` shells out to every guard in the table above and asserts exit 0 — one command, one implementation, no logic duplicated into the test suite. Reach for the individual scripts when you want the readable per-key findings instead of an aggregated PHPUnit failure.

### CI coverage vs manual runs

The backend workflow is `.github/workflows/ci.yml`, and it runs **only the checks that need one repo**, so it carries no credentials — no cross-repo token, no sibling checkout:

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

An earlier version of this workflow did check out the sibling and run all six. It never worked: the sibling is private, `GITHUB_TOKEN` is scoped to the repo running the workflow, and the checkout step failed `Not Found` before a single test executed — a red `main` caused by the gate itself, not by the corpus. Reading the sibling needs a fine-grained PAT, and the four guards still would not cover the SPA-only case (see below). The workflow was narrowed instead of patched.

### PR & merge process

1. Run the gates locally, from a checkout that has `monolith-vue-workers` as a sibling: `php artisan test --filter=Locale` (covers all six guards) and `npm run check:i18n` in the SPA.
2. Push and open the pull request.
3. Wait for the backend `locale` job and the SPA `check` job to go green.
4. Merge.

Step 3 is a convention, not an enforced rule. These repos are private and the org is on the GitHub **free** plan, so branch protection and required status checks are unavailable — `gh api repos/silverspoonmedia/monolith/branches/main/protection` answers `403 Upgrade to GitHub Pro or make this repository public`. Nothing on GitHub blocks a merge with a red run; merging anyway leaves `main` red, which is what happened to PR #77 and #10 when the cross-repo workflow above was in place. The gate protects the corpus only when someone looks at it.

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

User-facing **numbers and datetimes** (money separators, absolute dates, relative “2 hours ago”) follow `app()->getLocale()` via `App\Support\Presentation\{Date,Money,Byte}Presenter` at the API Resource layer. They are **not** translated through `lang/{locale}/*` string keys. API `*_formatted` and `*_human` companions remain authoritative for every API consumer.

SPA-native values that are not already formatted by the API use `Intl.NumberFormat(locale.value)` and `Intl.DateTimeFormat(locale.value, …)` with the active Vue locale. Do not call unqualified `toLocaleString()` for user-facing values.

Clients must not reformat API values — consume Resource companions (`*_formatted`, `*_human`). Contract: [platform/crud.md — API Resource presentation](crud.md#api-resource-presentation-dual-fields).

## Domain glossary

Canonical term choices across locales. Use these for every translation wave; do not vary per file. Brands and product names (`Archivd`, `vTual`, `Microstock`, `Discord`, `QRIS`, `YouTube`, `Twitch`, `hCaptcha`) are never translated. Machine/contract text (enum values, probe method names, `sha256`, URL placeholders) stays English.

| English | id | ja | ko | zh_CN | ar | ru |
|---------|----|----|----|-------|----|----|
| workspace | workspace | ワークスペース | 워크스페이스 | 工作区 | مساحة العمل | рабочая область |
| orphan (unowned entity) | yatim | オーファン / 孤立 | 고아 | 孤立 | يتيم | сирота |
| claim (ownership claim) | klaim | 申請 | 클레임 | 认领 | مطالبة | заявка (клейм) |
| verify (a claim's proof) | verifikasi | 検証 | 검증 | 验证 | تحقق | проверка |
| creator (vTual profile) | kreator | クリエイター | 크리에이터 | 创作者 | منشئ | автор |
| channel (platform, YouTube/Twitch) | channel | チャンネル | 채널 | 频道 | قناة | канал |
| channel (payment) | channel | 決済チャネル | 결제 채널 | 渠道 | قناة الدفع | платёжный канал |
| slug (URL-safe identifier) | Slug | スラッグ | 슬러그 | 标识 | المعرّف | Слаг |
| activation (product) | aktivasi | アクティベーション | 활성화 | 激活 | تفعيل | активация |
| entitlement | hak akses produk | エンタイトルメント | 이용 권한 | 授权 | استحقاق | право доступа |
| invoice | invoice | 請求書 | 인보이스 | 发票 | فاتورة | счёт |
| payment gateway | gateway pembayaran | 決済ゲートウェイ | 결제 게이트웨이 | 支付网关 | بوابة الدفع | платёжный шлюз |
| settlement | settlement | 精算 | 정산 | 结算 | تسوية | расчёт (сеттлемент) |
| upload batch | batch unggahan | アップロードバッチ | 업로드 배치 | 上传批次 | دفعة الرفع | пакет загрузки |
| Archivd | Archivd | Archivd | Archivd | Archivd | Archivd | Archivd |
| persona | persona | ペルソナ | 페르소나 | 形象 | شخصية | персона |
| role / permission | peran / izin | ロール / 権限 | 역할 / 권한 | 角色 / 权限 | الدور / الصلاحيات | роль / разрешение |
| staff | staf | スタッフ | 스태프 | 工作人员 | الموظفون / الموظفين | сотрудники |
| archive (verb) | arsipkan | アーカイブ | 보관 처리 | 归档 | أرشفة | архивировать |
| restore | pulihkan | 復元 | 복원 | 恢复 | استعادة | восстановить |
| connected accounts | akun terhubung | 連携アカウント | 연결된 계정 | 已连接账户 | الحسابات المتصلة | связанные аккаунты |
| two-factor authentication | autentikasi dua faktor | 二要素認証 | 2단계 인증 | 双重身份验证 | المصادقة الثنائية | двухфакторная аутентификация |
| notification | notifikasi | 通知 | 알림 | 通知 | إشعار | уведомление |
| save / cancel | simpan / batal | 保存 / キャンセル | 저장 / 취소 | 保存 / 取消 | حفظ / إلغاء | сохранить / отмена |
| loading | memuat… | 読み込み中… | 로드 중… | 加载中… | جارٍ التحميل… | загрузка… |
| trash / trashed | sampah / terhapus | ゴミ箱 / 削除済み | 휴지통 / 삭제됨 | 回收站 / 已删除 | سلة المهملات | корзина / удалённые |
| subscription / plan | langganan / paket | サブスクリプション / プラン | 구독 / 요금제 | 订阅 / 方案 | اشتراك / خطة | подписка / тариф |
| credential (API key, token) | kredensial | 認証情報 | 자격 증명 | 凭证 | بيانات الاعتماد | учётные данные |
| identity (domain group) | identitas | アイデンティティ | 아이덴티티 | 身份 | الهوية | идентификация |
| wallet / e-wallet | dompet / dompet digital | ウォレット / 電子マネー | 지갑 / 전자지갑 | 钱包 / 电子钱包 | محفظة / محفظة إلكترونية | кошелёк / электронный кошелёк |
| checkout | checkout | チェックアウト | 체크아웃 | 结账 | إتمام الشراء | оформление заказа |
| billing | penagihan | 課金 | 과금 | 计费 | الفوترة | оплата |
| probe (platform API check) | probe | プローブ | 점검 | 探测 | فحص | проверка |
| management (vTual entity) | manajemen | マネジメント | 매니지먼트 | 管理机构 | الإدارات | организация |
| session | sesi | セッション | 세션 | 会话 | الجلسة | сессия |
| asset (Microstock upload) | aset | アセット | 에셋 | 素材 | أصل | актив |
| queue (batch) | antrean | キュー | 대기열 | 队列 | قائمة الانتظار | очередь |
| upstream storage | penyimpanan | ストレージ | 저장소 | 存储服务 | التخزين | хранилище |

Glossary drift found while preparing the `zh_CN` wave — fix in the owning locale's wave, not ad hoc:

- Brand casing drifted in the *English source itself*: `web/vtual-vtuber.php` `claim_step_5` wrote the product as `Vtual` instead of `vTual`, and the parity gate cannot see it because every locale inherited the same wrong casing. Fixed in `en` first, then in `id`, `ja`, `ko`, `ru`, `zh_CN`, `ar` on the backend and in the SPA `src/i18n/locales/*.json`. The same comment-only drift (`// Vtual`) sat above the `vtual.*` settings block in all seven `web/settings.php` files and was corrected too. A brand spelled wrong in `en` is invisible to every per-locale guard — check the source when a brand looks off in one locale, because it is probably off in all of them.
- `slug` was split three ways in the SPA `zh-CN` file (`子弹` — literally "bullet" — in `validation.attributes.slug`, `Slug` in twelve vTual/inference keys, `别名` in five blog keys). The `zh_CN` wave normalized all of them to `标识` on both sides; `磁盘目标别名` in `api/archivd.proxy_misconfigured` is "disk target alias", not a slug, and stays.
- `credential` drifted inside the backend `zh_CN` files: `凭据` in `api/microstock.php`, `list_actions.php`, `web/payments.php`, `web/settings.php` vs `凭证` in `api/vtual.php`. Unified on `凭证`.
- `plan` drifted inside the backend `zh_CN` files: `套餐` in `api/archivd.php`, `api/services.php`, `web/archivd.php` vs `方案` in `web/payments.php`. Unified on `方案`.
- `channel` maps to two different Chinese words by domain (`频道` for a platform channel, `渠道` for a payment channel). Both are correct; do not unify them. `快速通道` in `web/settings.php` `hints.microstock.ai.phase.normal_after` and its SPA twin is "fast-track" and is likewise correct.
- `slug` in `ar` was split four ways and had to be repaired, not merely polished: `سبيكة` ("metal ingot") in 19 SPA keys including `validation.attributes.slug`, `المعرّف المختصر` ("abbreviated identifier") in the backend orphan/vTuber labels, `معرّف` in the backend connection/management phrases, and `نص صديق` ("friendly text") in `lang/ar/validation.php`. The `ar` wave unifies the standalone label on `المعرّف`; the phrase forms `معرّف الاتصال` / `معرّف الإدارة` stay as they are, because there the identifier is qualified by a noun and the definite article reads wrong. The closing pass re-measured it: `سبيكة` 0 on both sides, `المعرّف` 8 backend / 32 SPA.
- `slug` in the SPA `id` file was split between `Slug` (18 keys) and `Identitas URL` (8 keys) while the backend `id` files used `Slug` consistently. The `id` wave cleaned the SPA leftovers up; the closing pass re-measured it: `Identitas URL` 0 on both sides, `Slug` 11 backend / 26 SPA, and `Identitas URL` is now a `FORBIDDEN` variant in `locale-glossary-scan.php`.
- Two `ar` glossary rows were simply wrong and are corrected: `trash` was recorded as `المحذوفات` (0 occurrences on either side) when both sides use `سلة المهملات`, and `staff` was recorded as `فريق العمل` (0 occurrences) when both sides use `الموظفون` / `الموظفين`. The locale was consistent; the table was not.
- `ar` had a half-English sentence in `web.vtual.claims.user_create_note` (`...؛ الموظفين UI بذور الأيتام فقط.`) that no guard caught, because the value differs from English so the duplicate check skips it and it contains CJK-free Arabic so the script check passes. Leftover Latin words inside otherwise-translated prose are now the permanent `locale-prose-scan.php` guard (see [Curation tooling](#curation-tooling)) — the same class in `id` (`management` in 26 backend keys) and `zh_CN` (`Test` in 6) is what closed the residual list and let the gate be switched on.

Register: id (Anda, formal netral), ja (です/ます), ko (해요체 `-하세요` for instructions, 합쇼체 `-합니다` for declaratives), zh-CN (您, simplified), ar (MSA formal), ru (вы, lowercase after «вы» except at sentence start). Use plain verb forms for buttons; full sentences for toasts/messages.

The `ko` register is settled on **해요체 for instructions and 합쇼체 for declaratives**, not one level for everything. Before the `ko` wave the imperative forms were mixed — `-하십시오` and `-하세요` for the same kind of sentence — which reads as two different voices in one UI. Instructions, hints, and empty states now use `-하세요` (`다시 시도하세요`, `페이지를 새로 고치세요`); statements and toasts keep `-합니다`/`-되었습니다` (`저장되었습니다`, `필요합니다`). Do not "unify" the two into one level: a product that only ever speaks in `-합니다` reads like a manual, and one that only ever uses `-하세요` reads like a chatbot. `ko` also keeps `-하기` in the backend `actions.php` pack (`추가하기`) while the SPA `actions.*` block uses the bare noun (`추가`) — that is intentional; see the parity note above.

Glossary drift found while preparing the `ko` wave — fixed in the `ko` wave (Sep 2026):

- `ko` had **no** word for *checkout* or *billing*: both were written as `결제` ("payment"), so a checkout session and a payment read identically. 22 backend keys and 41 SPA keys affected. Every other locale keeps the distinction (`ja` チェックアウト / 課金, `zh-CN` 结账 / 计费, `id` checkout / penagihan, `ar` إتمام الشراء / الفوترة, `ru` оформление заказа / оплата). The `ko` wave moves them to `체크아웃` and `과금`. `과금` already existed in `ko` — `api.notifications.types.inference_unregistered_model` used it correctly — which is the evidence the term is the natural one for this locale rather than a coinage.
- `ko` `probe` was split between `점검` (13 keys) and `프로브` (1 key, `web.vtual-platform-credentials.probe_button`). Unified on the native `점검`; the other locales transliterate (`ja` プローブ) but `ko` had already settled on the native word. `ru` did the same in its own wave (`проверка`, not `проба`/`зонд`).
- `ko` transliterated the `Microstock` brand as `마이크로스톡` in 2 keys while using the Latin `Microstock` in 22. All six other locales keep the Latin brand. Unified on `Microstock`.
- `ko` date columns were verb forms where `ja` uses a date noun: `web.vtual-orphans.datatable.columns.created_at` and `web.vtual-managements.datatable.columns.created_at` were `생성됨` ("created") where `ja` writes 作成日, and `web.archivd.columns.modified` was `수정됨` where `ja` writes 更新日時. A table header needs the noun (`생성일`, `수정일시`).
- `ko` left two values untranslated in `lang/ko/validation.php`: `attributes.color` = `color` and `attributes.region` = `region`. The file carried zero curated flags, which is exactly why the parity gate never examined it — a zero-flag file is indistinguishable from a correct one unless the values are checked against `en`.
- Found while sizing the `ko` wave and fixed in the `ru` wave: `ru` had a `billing` drift of its own — see the `ru` drift list below.
- The `ko` claim flow used the wrong word for *claim* in two different ways. Ownership claim is `클레임`, but the SPA rendered it as `청구` (which is *invoice*, and `ko` uses `인보이스` for that) in 9 keys and as `소유권 주장` ("ownership assertion") in 10 more, while the backend already said `클레임`. Both are now unified on `클레임`.
- The `ko` claim *verification* step had drifted into three words across three files: `인증` in `lang/ko/api/vtual.php` (21 keys), `검증` in `lang/ko/web/vtual-vtuber.php` (20 keys), and `확인` in the `lang/ko/web/settings.php` claim keys. `인증` is already the established word for *authentication* (`2단계 인증`, `이메일 인증`), so reusing it for claim verification collides with the auth surface; `확인` is the generic "check". Unified on `검증`, which is precise and does not collide. Keep `인증` wherever the English is authentication, 2FA, or email verification — only the claim-verification sense changes.
- `ko` used two words for *suspended*: the backend `lang/ko/product-activations.php` said `일시 중단` (11 keys) while the SPA said `정지` (9 keys), so the same activation state had two names depending on the surface. Unified on `정지` / `정지됨` / `정지일시`, which is the shorter and more common product term, and kept `일시 중단` only where the English says *temporarily* suspended. The same pass de-collided the state *rejected*: `거부` (from the backend) and `거절` (in the SPA) were both in use, and `거절` was already the word for declining a management invite, so *reject* settled on `거부` and the invite flow keeps `거절`.
- `ko` called the activation `tier` both `티어` and `등급`. `등급` is also the word for a *role level* in the hierarchy messages (`api.users.policy.hierarchy_view`), which is a different concept, so the tier column, the tier badge, and the free/paid/grace metric labels moved to `티어` and `등급` is reserved for role level.
- `ko` `visibility` was `가시성` in two SPA keys (`web.domain-access.visibility_updated_to`, `failed_update_visibility`) and `공개 설정` / `공개 범위` on the backend. `가시성` is a literal calque; Korean product UI says `공개 범위` for a public/private setting. The backend `domain-access.php` column label was also `공개 설정` while the values are `공개`/`비공개`, so the label and its own values disagreed; both sides now read `공개 범위`. **The first attempt at this fix did not land**, and the note above claiming it had was written from the intent rather than from a re-read of the files: ten backend values and eleven SPA values still said `공개 설정` after the wave "closed". It was caught by the end-of-wave scan, not by any guard, because `공개 설정` is valid Korean and differs from English. `locale-glossary-scan.php` now enforces this term, which is the point of that script: a glossary claim that no guard checks is a claim, not a state.
- The `h-captcha-response` attribute label was missing in `ko`, `ar`, and `ru` (present in `en`/`id`/`ja`/`zh_CN`). Nothing surfaced it: the field is validated in `ValidatesHcaptcha`, the rule message `validation.hcaptcha` existed in all seven locales, and the label is only read when a validation error is *rendered* — so the user saw the raw field name `h-captcha-response` in an otherwise fully translated form. Found while re-checking the `ko` managed pack for untranslated values, the same way `attributes.color` and `attributes.region` were found; `ar` and `ru` were fixed in the same change because the defect is identical. Adding it to `ko` made the `ko` pack key-complete against `en`.
- `ko` had a straight typo in `menu.navigation`: `날비게이션` (an invalid word — `날비` is not Korean) in the SPA while the backend said `내비게이션`. Fixed on both sides. This is a class no key/placeholder guard can see, because the value is non-English and structurally valid.
- `ko` `access` was split between `접근` and `액세스` with no rule: the backend `domain-access.php` used `접근` (7 keys) and the SPA used `액세스` (5 keys) for the same sentences. `액세스` is a transliteration; `접근` is the native word and was already dominant. Unified on `접근` for the verb and the compound (`접근 권한`, `접근할 수 있습니다`).
- `ko` attached a particle straight to a placeholder (`:product가`, `:field를`, `:status를`, `:channel로`) in 8 runtime messages. That reads fine for one sample value and wrong for the next one, because the particle depends on the final consonant of whatever the caller substitutes — and the substituted values here are product names, secret-field keys, and HTTP status codes, which do not share an ending. Both sides now use the parenthesised form the SPA already had (`:product이(가)`, `:field을(를)`, `:status을(를)`), which is the standard Korean convention for an unknown noun. Where the sentence can avoid the problem entirely, it does: `:channel을(를) 통해 결제하면` instead of `:channel로 결제하면`. Particles that do not vary (`의`, `에`, `님`, `께서`) are fine attached and stay that way.
- `ko` `handle` was `핸들` in the backend but left as the Latin `handle` in the SPA `web.vtual-orphans.channels.profile_help` and `web.vtual.profile_help_youtube`; both sides now say `핸들`. The `@handle` inside `profile_placeholder_youtube` stays Latin, because that is a literal example of the input the field accepts.
- `ko` `open_website` said `웹사이트로 열기` in the backend `actions.php` and `웹사이트에서 열기` in the SPA and the flat pack. `-에서 열기` ("open on/in") is correct for a link that opens a site, and was already the majority form. Unified on `웹사이트에서 열기`.
- `ko` `all_rights_reserved` was `모든 권리 보유.` — a calque of the English word order. `ja` and `id` both use the natural copyright formula for their language, so `ko` moved to `무단 전재 및 재배포 금지.`, the standard Korean notice.
- `ko` `Discard` in the flat pack was `취소`, identical to `Cancel` — the two buttons were indistinguishable. Changed to `버리기`, matching the SPA `actions.discard`.
- `ko` carried 76 flat-pack scalars that duplicate `lang/ko/http-statuses.php` and had drifted from it in 20 places (`Found` `발견됨` vs `임시 이동`, `Gone` `사라짐` vs `존재하지 않음`, `Forbidden` `권한 없음` vs `금지됨`, `Unauthorized` `인증되지 않음` vs `인증 실패`, `Too Many Requests` `너무 많은 요청` vs `요청이 너무 많음`, …). The pack copies were older machine text; the curated `http-statuses.php` wording now wins in the flat pack too, so the same status code cannot render two ways. The closing pass measured the same class again across all six locales and found `ko` still carried 16 drifted `actions` scalars plus the 16 the `ru` list below records — all mirrored to the canonical owner, which is what took the `ko` row of `locale-flat-pack-parity.php` to 0.

Glossary drift found while preparing the `ru` wave — fixed in the `ru` wave (Sep 2026). `ru` was the last locale on pre-wave machine text, so it carried the largest set; every claim below was re-read from the files after the batch, not inferred from the batch:

- **`checkout` and `billing` both rendered as `оплат`.** The backend `web/payments.php` `checkout_hint` said `Поведение при оформлении оплаты`, and the SPA used three forms for one concept (`оформление оплаты`, `оформление заказа`, `Оплата заказа`). Settled on the glossary split — checkout = `оформление заказа`, billing = `оплата` — on both sides. `оформление оплаты` is now a forbidden substring in `locale-glossary-scan.php`, which is what keeps the two from collapsing again.
- **`billing` was transliterated as `биллинг`** in `lang/ru/web/settings.php` (`fields.microstock.ai_billing.markup_multiplier`, `hints.platform.inference.default_input_per_million_usd`, `hints.platform.inference.unknown_input_per_million_usd`) and its SPA twins, while the other 44 occurrences in `web/payments.php` alone said `оплат`. All moved to `оплата`.
- **`probe` was split four ways**: `зонд` (5 backend keys in `api/vtual.php`), `Проба` (3 SPA), `Зонд` (3 SPA), and `Проверка API` (1 SPA). Settled on `проверка` — the native word, and the one the SPA already used most. The glossary row said `проба` and was wrong; it now reads `проверка`, and `зонд` is banned.
- **`tier` was `уровень` in `product-activations.php` and the SPA but `тариф` in `web/archivd.php`** (`badge_free` = `Бесплатный тариф`) for the same free/paid/grace metric. `тариф` is already the glossary term for *plan/subscription*, so it was the collision, not the fix. The metric stays `уровень` and `тариф` is reserved for plans.
- **Date columns were participles agreeing with a hidden noun.** The SPA `created_at` appeared as `создано в`, `Создано в`, `Создано`, and `Зафиксировано`; `modified` was `Изменено`; `expired_at` was the present-tense `Истекает`. A table header needs a stable label, so both sides now use date nouns: `Дата создания`, `Дата изменения`, `Дата удаления`, `Дата начала`, and `Действует до` for an expiry column. `validation.attributes.created_at` / `deleted_at` / `updated_at` keep their participle forms (`создано в`), because those are interpolated into validation sentences, not used as headers.
- **`discard` collided with `cancel`.** The SPA `actions.discard` was `Отменить` where `actions.cancel` is `Отмена`, so two different buttons read the same. The backend `actions.php` already had the right word; the SPA moved to `Отказаться`. Separately, the SPA `actions.impersonation` was the transliteration `Имперсонация`, and the backend's `Войти под пользователем` won. `Имперсонац` is banned.
- **`claim` used the wrong nouns and the wrong verb.** `заявление` ("statement") and `претензия` ("complaint/legal claim") both appeared where the entity is a `заявка`; the verb was `претендовать` where the action is `заявить права`. Both are banned, and the settled pair (`заявка` for the noun, `заявить права` for the verb) is floored.
- **`management` (the vTual entity) was `менеджмент`**, an uncountable collective noun that had been pluralized and declined into `менеджменты` / `менеджментами` — ungrammatical in Russian. Settled on `организация` on both sides (the entity has owners, members, and invites, which is what `организация` means), and `менеджмент` is banned. The `creator` inside it moved from the transliteration `креатор` to `автор` in the same pass.
- **`session` was `сеанс`**, which in Russian is the cinema/theatre sense; a login session is `сессия`. The SPA's `ui.ajax.session_expired` and `web.auth.login_heading` both said `сеанс`. Settled on `сессия`; `сеанс` is banned.
- **`service` was `Служба`** in SPA keys where the product word is `Сервис` (the SPA page is titled `Сервисы`), and `AI` was written in Latin in some SPA keys while the backend said `ИИ` throughout. Both settled (`Сервис`, `ИИ`) and both banned as variants. `AI-` and ` AI ` are the banned forms so the brand `vTual`/`Microstock` Latin text is untouched.
- **`workspace` was the calque `рабочее пространство`** in one SPA key (`web.services.microstock.slot_desc`) while the settled term is `рабочая область`. All four declensions of the calque are banned.
- **`payment channel` drifted to `канал оплаты`** in three SPA keys (`channels.load_failed`, `channels.update_failed`, `select_payment_channel`) where the term is `платёжный канал`. Fixed and banned. `Способ оплаты` is deliberately **not** banned: the English keys it renders are literally `Payment method` (`checkout.select_channel`, `invoices.fields.payment_method`, `show.payment_not_started`), which is a different concept from a channel.
- **`Origin SPA` in `api/identity.php` `oauth.spa_url_missing`** was the one leftover-Latin defect in prose; `Origin` is not a Russian word and the SPA-origin sense is `Источник`. The other 123 Latin-bearing values were reviewed and kept: the `php artisan payments:sync-routing-policy` command, `path-style S3/B2`, `flat-lay`, `DMCA`, `WebSockets`/`PUSHER_*`, `IPv4`/`HEX`/`URI`/`SSL`/`Railgun`, `GET`/`HEAD`, `CSV`, `vTuber`, `Enter`, `micro-USD`, `px`.
- **`validation.attributes` hygiene**: `slug` was lowercase `слаг` where the glossary term is `Слаг`, and `email` was the mixed-script `email адрес`. Both fixed; the rest of the lowercase attribute labels are correct, because they are interpolated mid-sentence (`Поле :attribute обязательно.`) and are exempt from the capitalization rule.
- **`Пожалуйста` filler** appeared in backend `web/payments.php`, the SPA, and the flat pack. Russian product UI does not use it; the imperative alone carries the politeness (`Выберите другой.`). Removed everywhere and banned.
- **Brand casing**: `web/archivd.php` `microstock.error_image_only` said `В коллекции microstock` while the brand is `Microstock` in 22 other places in the same file set. Fixed.
- **The flat pack `lang/ru.json` had never been curated.** `Admin` = `Панель администратора` was the clearest defect — the key is the role noun, so it is `Администратор`, never the panel — and `Please click the button below…` carried the `Пожалуйста` filler. 57 keys curated, recorded in `backend-curation-log.json` because JSON carries no comments.
- **The `ru` flat pack still carried 55 scalars that had drifted from their PHP owners** (37 `http-statuses`, 16 `actions`, 2 `api`) — the `ru` wave curated the pack's own wording but never mirrored it to the owner files, so `lang/ru.json` `Found` and `lang/ru/http-statuses.php` disagreed. The closing pass mirrored each scalar to its canonical owner, taking the `ru` row of `locale-flat-pack-parity.php` to 0 and `lang/ru.json` to 117 logged keys of 228.
- **The four mirrored packs were one laravel-lang generation behind the SPA** (validation 179 divergent, http-statuses 48, auth 3, passwords 4), and some backend entries were genuinely wrong rather than merely different: `417` duplicated `408` (`Истекло время ожидания`), `419` was `Сессия устарела` where the SPA had `Сессия истекла`, and `423` was `Доступ заблокирован` where the SPA had `Заблокировано`. One wording was settled per key and mirrored, driving `locale-pack-parity.php ru` from 234 to 0. This was a settle-then-mirror job, not a "copy one side" job — neither copy had been reviewed.

  Two guards now cover what the parity gate cannot: `locale-glossary-scan.php` holds the banned variants and the occurrence floors for `ru` (and `ko`), and the end-of-wave scan in `.tmp-i18n/` covers the residual families the strict rules cannot express — missing `ё` (`учетн` → `учётн`), lowercase sentence starts that are genuinely fragments, and Latin words inside otherwise-translated prose. The scan is a review list, not a pass/fail gate: 240 backend and 147 SPA values legitimately start lowercase, almost all of them `validation.attributes.*` labels plus badges (`осталось`), placeholders (`например, abc123,def456`), and mid-sentence continuations (`или нажмите для выбора`).

Glossary drift found by the closing pass — fixed in that pass (Sep 2026). The closing pass also settled the framework packs and the flat pack for `id`, `ja`, `zh_CN`, and `ar`, so these lists are read together with the mirror policy in [Packages & parity](#packages--parity).

`id`:

- **`management` and `creator` were left as English words inside Indonesian sentences** in `lang/id/api/vtual.php` — `Nama management wajib diisi.`, `Kreator berhasil ditugaskan ke management.`, `Mode undangan creator harus "existing" atau "new".` — 26 backend keys and 20 SPA keys. No structural guard can see this class: the value differs from `en`, so the duplicate check skips it, and the locale is Latin-script so a script scan finds nothing either. The settled loanwords are `manajemen` and `kreator`; the `:management`/`{management}` **placeholder keeps its English name**, because placeholders are contract text, not copy. Floored at `manajemen` 21/34 and `kreator` 10/29.
- **`403 Forbidden` was `Dilarang` and `401 Unauthorized` was `Tidak Diizinkan`** — literal calques of the English. Settled on `Terlarang` and `Tidak sah`, which is what the SPA already used. Both calques are banned.
- **`expired` was `Kadaluwarsa`**, a misspelling of the KBBI form `kedaluwarsa`. Banned, and `kedaluwarsa` is floored at 16/19.
- **`slug` split in the SPA file** — see the `zh_CN` list above; `Identitas URL` is banned, `Slug` floored at 11/26.
- **The flat pack `lang/id.json` had 10 curated keys of 228.** The closing pass mirrored its 189 owned scalars to their canonical owners and curated the 39 unowned ones, taking it to 94 logged keys.

`ja`:

- **Two glossary rows were wrong and are corrected.** `claim` was recorded as `クレーム`, which is a false friend — in Japanese it means "complaint", not an ownership claim — while the corpus uses `申請` in 84 backend and 152 SPA values. `orphan` was recorded as `孤児 / 孤立`; the corpus uses the transliteration `オーファン` (6/16) and keeps `孤立` only for the Archivd `孤立パート` ("orphan part") settings label. Both rows now match the corpus, and `クレーム` is banned.
- **The `Actions` column rendered two ways inside one locale.** `product-activations.col_actions` was `アクション` while the other six namespaces that label the same column (`actions`, `ui.labels.actions`, `web/archivd` blocklist and columns, `web/payments` invoices and subscriptions, `web/vtual-orphans`) use `操作`. Unified on `操作` on both sides.
- **`View` rendered two ways.** `actions.php` said `閲覧` ("browsing") while `list_actions`, `ui.datatable.view`, and `web/archivd.view` say `表示`. Unified on `表示` — the majority form, and the standard Japanese UI word for a display action. The SPA's generic `actions.view` stays `閲覧`, which is the documented SPA-vs-pack difference, not drift.
- **`403 Forbidden` = `アクセス禁止` and `302 Found` = `一時的な移動`** are settled and floored at 2/1 each. They cannot be banned as phrases: `発見` is the ordinary Japanese word for "discovery" and is correct in the Discord `発見チャンネル` keys, and `禁止されています` is the correct validation sentence (`:attributeの入力は禁止されています。`), so the floor is the tripwire instead.
- **The flat pack `lang/ja.json` had 11 curated keys of 228**; the closing pass took it to 78. Its 30 flat-pack divergences (15 `http-statuses`, 13 `actions`, 2 `api`) were closed by mirroring the owner, and `302 Found` was the visible defect — `発見` ("discovery") as the reason phrase for a redirect.

`zh_CN`:

- **`Detach` was `联系` ("contact")** where the action is unlinking — settled on `解除关联` on both sides. The closing pass re-measured it: `解除关联` 2/1, and the remaining `联系` values (2/2) are the legitimate "contact support" sense (`请认领现有资料，或联系工作人员为您创建。`), so the word is **not** banned.
- **`Archive` was the noun `档案`** where the verb is `归档`. Re-measured: `档案` 0 on both sides, `归档` 26/45. `档案` is banned as a tripwire.
- **`slug`, `credential`, and `plan` were each split inside `zh_CN`** — `子弹` ("bullet"), `凭据` vs `凭证`, and `套餐` vs `方案`. All three are settled (`标识`, `凭证`, `方案`) and all three rejected variants are banned. `别名` is deliberately **not** banned: `磁盘目标别名` is "disk target alias" and is correct.
- **`log out`, `show all`, and `like` had literal renderings**: `登出` for `退出登录`, `显示所有` for `全部显示`, `喜欢` ("be fond of") for the `赞` vote. All three settled; all three literal variants banned.
- **The flat pack `lang/zh_CN.json` had 1 curated key of 228.** The closing pass mirrored 189 owned scalars and curated the 39 unowned ones, taking it to 114 logged keys, which is the largest flat-pack pass after `ru`.
- **The four mirrored packs were fully divergent** — 150 `validation` keys, 19 `http-statuses`, 3 `auth`, 3 `passwords` — and both copies were already curated (137 and 150 flags), so this was drift between two reviewed copies rather than a missing pass. One wording was settled per key and mirrored, driving `locale-pack-parity.php zh_CN` from 175 to 0. The `validation` pass also carried the laravel-lang generation defect the `id`/`ja` copies had: capital `:Attribute` placeholders and the mixed-style `any_of` sentence.

`ar`:

- **`410 Gone` was `ذهب`** — the verb "he went", picked up as a literal rendering of *gone*. Settled on `محتوى محذوف نهائيًا` ("permanently removed content") and banned.
- **The `verify` glossary row said `تحقّق`** (with a shadda) while the corpus writes `تحقق` in 68 backend and 110 SPA values. The row was wrong, not the corpus; it now reads `تحقق`, which is floored.
- **`actions.php` was the odd copy of three labels.** `Actions` was `إجراءات` (indefinite) while the SPA and the other seven namespaces use `الإجراءات`; `Settings` was `إعدادات` while `web/settings.title` and the SPA use `الإعدادات`; and `Done` was `مكتمل` while `web/archivd.upload.done` and the SPA use `تم`. All three fixed in the owner file, and the flat pack re-mirrored.
- **The flat pack `lang/ar.json` had 0 curated keys of 228** — the only locale with none. The closing pass mirrored its 189 owned scalars and curated the 39 unowned ones, taking it to 87 logged keys. The visible defects were `410 Gone` = `ذهب` and the `Admin` scalar, which the `ru` list above records for the same reason (the key is the role noun, never the panel).
- **Orthography and loanword variants are recorded as banned** even though the corpus already had 0 occurrences of each, so the bans are tripwires rather than fixes: `الإستضافة` → `الاستضافة` (hosting takes a plain alef), `الإيميل` → `البريد الإلكتروني` (the transliteration, not the Arabic term), `الإنتقال` → `إعادة توجيه` (redirect, and the variant carries a wrong hamza seat), and `عذراً` → `عذرًا` (the hamza-less apology).
- **Two senses are kept deliberately and must not be unified**: `Admin` is `مدير` as the generic role noun (flat pack, `actions.php`, the SPA `actions.*`) but `مشرف` as the workspace member badge in `web/archivd.collab.role_admin_short`; and `Files` is `ملفات` as the generic noun but `الملفات` as the `product-activations` metric column. The same split exists for `User` (`مستخدم` generic vs `المستخدم` column header).

Multi-owner keys, closing decision:

- The 29 flat-pack scalars with more than one PHP owner are resolved by the precedence in the mirror policy above. Where the owners differ for the **same** concept the owner file was fixed instead of the pack (`ar` `Actions`/`Settings`/`Done`, `ja` `Actions` column and `View`, `ko` `Create` in the Archivd dialog).
- Where they differ by **part of speech or sense** both are kept and the flat pack takes the action sense, because its consumers are buttons and menu items: `Archive` (verb `أرشفة`/`Arsipkan`/`보관하기`/`Архивировать`/`归档` vs the zip content type `أرشيف`/`Arsip`/`압축 파일`/`Архив`/`压缩包`), `Created` (event or HTTP status vs the `created_at` timestamp column), `Open` (button vs the error-log `Open` status), `OK` (the dialog button vs the HTTP 200 reason phrase), `Processing` (`ar` `جارٍ المعالجة` vs `قيد المعالجة`, `id` `Memproses` vs `Diproses`), `Export` (`ru` infinitive `Экспортировать` in the pack vs the noun `Экспорт` in the upload wizard step), `View` (`ru` `Просмотреть` vs `Просмотр`), `Connect` (generic `اتصال`/`接続`/`연결하기` vs account linking `ربط`/`連携`/`연결`), `Files` (generic vs metric column), and the `ko` `actions.php` `-하기` forms against `ui.php`'s bare nouns — which is the existing `ko` register decision, not a defect.
- No further PHP file changes are outstanding for these keys: `locale-flat-pack-parity.php` reports 0 for all six locales, and its `OWNER_OVERRIDES` (`OK`, `File`, `Connect`) record the three cases where the precedence order alone would have picked the wrong sense.

## Related

- [web/blade-admin.md](../web/blade-admin.md)
- sibling [i18n.md](../../../monolith-vue-workers/docs/i18n.md) — SPA JSON locales, runtime switching, fallback translation, `useI18n()` context, Sweetalert2 chrome keys (`ui.swal.*`, `ui.dialog.*`)
- [integration/vue-workers-spa.md](../integration/vue-workers-spa.md) — cross-repo contract pointer
- [domains/navigation/README.md](../domains/navigation/README.md) — Spatie menu fields
