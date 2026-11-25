# Retired locales — what their translation waves taught

**Status:** archive. `cored-backend` ships `en` (the source) and `id` only. `ja`, `ko`, `zh_CN`, `ar`, and `ru` were removed with `php artisan locale:remove ja ko zh_CN ar ru` (November 2026), inherited from the `monolith` line this fork was cut from.

**Why keep this page:** the wording decisions below are the expensive part of a language, not the file layout. Adding one of these locales back starts from a settled register, a settled glossary, and a list of defects that already shipped once — not from a blank slate. The live contract is [platform/reference-localization.md](../platform/reference-localization.md).

## Where the settled wording still lives

| Surface | What survives the removal |
|---|---|
| `lang/{en,id}` | the two remaining languages |
| [naming-conventions.md](../platform/naming-conventions.md) | the domain glossary and the register notes per locale |
| `scripts/lib/locale-latin-allowlist.php` | the prose-scan policy map for `ja`/`ko`/`zh_CN`/`ar`/`ru` (machine tokens, accepted loanwords) |
| `scripts/locale-glossary-scan.php` | `FORBIDDEN` and `MIN_OCCURRENCES` maps for the retired codes; the scan intersects them with `config('localization.supported_locales')`, so they never run today |
| Git history | `lang/{locale}/**`, `vendor/silverspoonmedia/*/resources/lang/{locale}/**`, and `src/i18n/locales/{locale}.json` at the commit before `locale:remove` |

## Register (unchanged, still normative if the locale returns)

- `id` — neutral formal *Anda*.
- `ja` — です/ます.
- `ko` — 해요체 for instructions (`-하세요`) but 합쇼체 (`-합니다`) for declaratives; never one level for both.
- `zh_CN` — 您, simplified.
- `ar` — MSA formal.
- `ru` — *вы*, lowercase except at sentence start.

## Defects that shipped once

- **Calque.** `id` `403 Forbidden` → `Terlarang`, not `Dilarang`; `401` → `Tidak sah`, not `Tidak Diizinkan`.
- **False friend.** `ja` `claim` as `クレーム` reads as "complaint"; the ownership-claim surface needed `申請`.
- **Literal metaphor.** `zh_CN` `slug` as `子弹` ("bullet") → `标识`; `ar` `410 Gone` as `ذهب` ("he went") → `محتوى محذوف نهائيًا`; `ja` `302 Found` as `発見` ("discovery") → `一時的な移動`.
- **English left inside non-English prose.** `id` `management`/`creator` stayed English in 20 SPA keys and 26 backend keys → `manajemen`/`kreator`. Invisible to structural guards: the value differs from `en`, and the locale is Latin-script.
- **One source phrase, two contexts, one key.** Split the key instead of writing a sentence that is wrong on both surfaces.
- **Term drift inside one locale.** `id` `forms.users.role_not_assignable` once shipped `Role yang dipilih…` where the settled term is `Peran`.
- **One curated side is not a curated pack.** `zh_CN` had 137 and 150 curated flags on the two copies of the mirrored `validation` pack and still disagreed on 150 keys: two independent curations drift. `ar` reported `0` for a whole session while `validation.attributes.h-captcha-response` was `كلمة التحقق` on the backend and `الكابتشا` in the SPA.
- **Cross-locale parity is the only closer.** `locale-pack-parity.php` returned `zh_CN` to 0 only after one wording per key was settled and mirrored, not by curating each side separately.

## The last known state before removal

- Backend curated flags: `id` 1276, `ja` 730, `zh_CN` 774, `ar` 264, `ko` 470, `ru` 819 (`en` 5 — source comments only).
- SPA logged keys: `id` 1090, `ja` 1071, `zh-CN` 1429, `ar` 1870, `ko` 1484, `ru` 835, `en` 54.
- Flat-pack curated keys (`backend-curation-log.json`): `ru` 117, `zh_CN` 114, `ko` 101, `id` 94, `ar` 87, `ja` 78 of 228.
- All three cross-repo guards were at 0 divergent for every locale when the removal started.
