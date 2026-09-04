# Localization (multi-language)

Cookie- or user-preference-driven locale for API and DB content. **No route prefix** (`/en/...`). The sibling Vue-Workers SPA has its own JSON locale runtime and startup resolution; its contract lives in [../integration/vue-workers-spa.md](../integration/vue-workers-spa.md).

**Canonical supported locales:** backend codes `en`, `id`, `ja`, `ko`, `zh_CN`, `ar`, `ru` (SPA/browser: `zh-CN` ↔ backend `zh_CN`). All other docs link here instead of restating the list.

## Architecture

| Layer | Tool | Use for |
|-------|------|---------|
| Package defaults | `laravel-lang/common` | `auth.php`, `validation.php`, … |
| Application strings | `lang/{locale}/…` | `ui.php`, `web/*`, `api/*`, `menu.php`, … |
| Database content | Spatie translatable | JSON fields (`Menu.name`, …) |
| Vue-Workers SPA | `monolith-vue-workers/src/i18n/locales/{en,id,ja,ko,zh-CN,ar,ru}.json` | SPA labels, errors, headings, statuses, and contextual UI copy |

**Never** put app strings in package-managed files (`lang:update` overwrites them).

`localization.md` owns Laravel PHP locale files, API resolution, and parity rules. [`vue-workers-spa.md`](../integration/vue-workers-spa.md) owns SPA JSON keys, runtime switching, module-level fallback translation, and Worker hydration. Keep those contracts linked instead of duplicating them.

### Task rule (required)

Any user-facing copy change includes **all** supported locales (`en`, `id`, `ja`, `ko`, `zh_CN`, `ar`, `ru`) in the **same** task/PR.

Every translation must be contextual, not literal. Review copy against its surface (button, heading, toast, validation, status, or explanatory text), audience, domain glossary, and locale-specific grammar/register. Preserve placeholders, plural behavior, and machine-contract text exactly where required; split keys when one source phrase serves different UI contexts. Literal wording that sounds unnatural in Indonesian or Japanese is not accepted.

Translation workflow:

1. Keep English as the reference/source locale unless key design or context separation requires a source update.
2. Identify surface, audience, domain term, register, placeholders, and plural rules before translating.
3. Review Indonesian and Japanese for natural product language, not word-for-word equivalence.
4. Run locale parity and placeholder tests before review; test locale switching where copy is reactive.
5. Update affected domain documentation when terminology or translation workflow changes.

| Touch | Translate in |
|-------|----------------|
| Remaining PHP `web/*` strings (Discord callback, CSRF, FH errors) | `lang/{locale}/web/*` |
| SPA labels | sibling `monolith-vue-workers` JSON locales |
| Query-mode row-action labels / confirms | `lang/{locale}/list_actions.php` (plus reused `ui.*` / `web/*`) |
| API messages / policy / business exceptions | `lang/{locale}/api/*` |
| FormRequest messages | `forms/*` or `__()` in request |
| Translatable DB fields | Spatie + admin input per locale |
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

### Web ↔ API sync

The SPA sends the active backend locale via `X-SPA-Locale` on API requests. The header carries `zh_CN` when the SPA is using `zh-CN`; other locale codes pass through unchanged. The header wins over the `locale` cookie (see resolution order above) so a stale cookie cannot pin the old locale after an abort-prone direction-swap reload; the cookie remains the fallback for guests and non-SPA requests.

Authenticated clients may also call `POST /api/v1/auth/locale`. Resolution order is db → header → cookie → default (**not** `Accept-Language` alone).

### SPA startup resolution

Vue-Workers resolves its initial locale independently of Laravel middleware:

1. Supported `localStorage.locale` (SPA code; `zh-CN` is canonical in browser storage).
2. First matching supported `navigator.language` / `navigator.languages` alias.
3. `en`.

Only `en.json` ships in the SPA's eager bundle; the other six locales load as dynamic-import chunks on first `setLocale()` (`LAZY_LOCALES` in `monolith-vue-workers/src/i18n/index.ts`), with eager `en` covering missing keys until the chunk resolves. Details: [vue-workers-spa.md](../integration/vue-workers-spa.md).

`html[lang]` uses the SPA code (`zh-CN`, not `zh_CN`), while browser `Intl` APIs receive the SPA code. Arabic also sets `html[dir="rtl"]`; all other supported locales set `ltr`. The SPA loads a direction-split global CSS entry (`main-ltr.css` / `main-rtl.css`, resolved before mount from the detected locale); the RTL entry imports `vendor-rtl.css`, a sibling of `vendor.css` that swaps Bootstrap/AdminLTE for their official `.rtl` dist builds (a separate CSS entry, not a Vite `resolveId` alias, because Vite runs CSS `@import` through postcss rather than the JS resolve pipeline). Switching locale across directions (Arabic ↔ any other) therefore triggers a full page reload from `setLocale()` so the other stylesheet loads. Directional icons follow a fixed convention: glyph swap for `bi-arrow-left` back buttons, `scaleX(-1)` for the custom `.fh-breadcrumb__sep` separator, and `isRtl()`-bound classes for list-movement prev/next — AdminLTE's own `.nav-arrow` is mirrored by the vendor RTL build and must NOT get a custom transform (details in [docs/integration/vue-workers-spa.md](../integration/vue-workers-spa.md)).

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

`web/*.php` shrank with the Blade removal — it now holds only strings that surviving **server-side PHP** still resolves: `session`, `identity-connections`, `vtual-vtuber` (Discord callback flash), `payments`, `file-hosting` (direct-upload errors), `settings` (registry labels), and `vtual-orphans` (orphan archive confirm, reused by query-mode `OrphanCreatorQueryRowActions` and the unused Yajra resolver — see [list-display-modes.md](list-display-modes.md)). Query-mode row-action labels and mutate confirms that are not already in `ui.php` / `web/*` live in `list_actions.php` (API-emitted; the SPA displays them, it does not re-translate). Browser UI strings live in the SPA's JSON locales, not here. Do not add a `web/*.php` file for a screen; if only the SPA reads it, it belongs in `monolith-vue-workers/src/i18n/locales/*.json`.

## Key conventions

| Context | Example |
|---------|---------|
| Shared UI | `__('ui.actions.save')` |
| Query-mode row actions | `__('list_actions.manage')` — labels/confirms emitted on `actions[]` |
| Server `web` strings | `__('web/session.expired')` — only the handful of files still reached by server endpoints (see below) |
| API domain | `__('api/navigation.created')` |
| Static nav labels | `__('menu.dashboard')` |
| DB menus | `$menu->name` (Spatie) |
| SPA JSON | `t('ui.swal.ok')`, `t('ui.dialog.error_title')` — Swal chrome in `monolith-vue-workers/src/i18n/locales/*.json`; domain confirm bodies stay on their domain keys. Contract: [vue-workers-spa.md — Notifications & confirmations](../integration/vue-workers-spa.md#notifications--confirmations-sweetalert2) |
| SPA chrome (nav) | `t('ui.navigation.load_failed')` — Valkivid3 nav-load fallback used by `useVkNavigation`. Catalog: `ui.error_state.*`, `ui.empty_state.*`, `ui.datatable.*`, `ui.navigation.*` live in the SPA JSON; full enumeration in [vue-workers-spa.md](../integration/vue-workers-spa.md) |

## Packages & parity

- `php artisan lang:add …` / `lang:update` for managed packs
- `tests/Unit/Localization/LocaleParityTest.php` checks supported locale directories, application-owned file presence, dot-notation key parity, and PHP placeholder parity. It excludes files listed in `config/localization.php` / package-managed basenames, including `validation.php` from placeholder comparison. Managed files are still required to exist for every supported locale and need a deliberate translation review after `lang:update` or package upgrades.
- Backend coverage inventory is generated with `php scripts/backend-locale-audit.php backend-locale-report.json`. It compares every application-owned PHP file against `en`, reports missing/extra keys, and reports exact English carryover while excluding identifier-like values and machine fields. The generated report is per file and locale; it is review output, not a translation allowlist. As of the 7-locale completion (Sep 2026) the audit reports zero missing/extra keys; the only remaining copy-candidates are intentional format placeholders (`UC… / @handle / https://youtube.com/@handle`, `12345678 / login / https://twitch.tv/login`) and technical option labels (`HTTP polling`, `WebSocket (realtime)`, `File Hosting`). `php scripts/locale-report-query.php <locale> [--verbose] [--file=<substr>]` queries the report per locale/file.
- `lang/{locale}.json` note: `en.json`/`id.json`/`ja.json` carry 16 nested group objects (`actions`, `api`, `ui`, `web`, …) that duplicate the PHP namespaces — historical `laravel-lang` layout. The four newer locale JSONs (`ko`, `zh_CN`, `ar`, `ru`) intentionally ship flat keys only; the nested groups are redundant with `lang/{locale}/*.php` and are not required.
- Translation waves run from the audit/report worklists with a per-locale/per-namespace batching helper (`scripts/locale-report-query.php` backend, `scripts/split-worklist.mjs` + `scripts/dup-query.mjs` SPA-side). No machine-translation cache is used; prior Google-Translate draft artifacts were rejected and deleted. All translations are produced contextually against the English source plus the consuming code surface, and follow the [Domain glossary](#domain-glossary) below.
- Vue-Workers `npm run check:i18n` checks JSON key parity across all seven SPA locales, Vue interpolation tokens, malformed braces, exact English duplicates, and hardcoded fallback-copy patterns under `src/`. Duplicate-English values fail unless their complete key appears in the explicit allowlist inside `scripts/check-i18n.mjs`; allowlist entries cover brands, provider/code identifiers, `QRIS`, `Discord`, `vTual`, `Microstock`, machine-contract text (format placeholders, probe method names, `sha256`, `{count} / 2000` hints, `YYYY-MM-DD`), and **locale-specific loanwords** whose accepted term in the locale is the English word itself (Indonesian `Status`/`Filter`/`Model`/`Margin`/`Label`/`Avatar`/`Channel`/`Platform`/`Gateway`/`Slug`/`Navbar`/`Sidebar`/`Checkout`/`Upgrade`/`Downgrade`/`Addon`/`Host`/`Marketplace`/`Token`/`API key`/`Access token`/`Refresh token`/`Client secret`/`Email`/`Bank`/`File`/`Audio`/`Video`/`Thumbnail`/`Subtotal`/`Total`/`Settlement`, and Chinese `Slug`). When a new loanword carryover appears, either translate it (preferred when a natural native term exists, e.g. `Identitas`) or add its complete key to the allowlist with the loanword note. The checker prints per-locale/two-segment-namespace counts so each translation wave can close one file/namespace at a time. `node scripts/i18n-duplicate-report.mjs` writes `i18n-duplicate-report.json` with locale/namespace/key-level counts, classifications, placeholder tokens, actionable totals, and stale-allowlist detection. The report is generated inventory, not permission to retain untranslated copy. SPA CI runs this before `build-only`.

## Number & date display (not lang files)

User-facing **numbers and datetimes** (money separators, absolute dates, relative “2 hours ago”) follow `app()->getLocale()` via `App\Support\Presentation\{Date,Money,Byte}Presenter` at the API Resource layer. They are **not** translated through `lang/{locale}/*` string keys. API `*_formatted` and `*_human` companions remain authoritative for every API consumer.

SPA-native values that are not already formatted by the API use `Intl.NumberFormat(locale.value)` and `Intl.DateTimeFormat(locale.value, …)` with the active Vue locale. Do not call unqualified `toLocaleString()` for user-facing values.

Clients must not reformat API values — consume Resource companions (`*_formatted`, `*_human`). Contract: [platform/crud.md — API Resource presentation](crud.md#api-resource-presentation-dual-fields).

## Domain glossary

Canonical term choices across locales. Use these for every translation wave; do not vary per file. Brands and product names (`vTual`, `Microstock`, `Discord`, `QRIS`, `YouTube`, `Twitch`, `hCaptcha`) are never translated. Machine/contract text (enum values, probe method names, `sha256`, URL placeholders) stays English.

| English | id | ja | ko | zh_CN | ar | ru |
|---------|----|----|----|-------|----|----|
| workspace | workspace | ワークスペース | 워크스페이스 | 工作区 | مساحة العمل | рабочая область |
| orphan (unowned entity) | yatim | 孤児 / 孤立 | 고아 | 孤立 | يتيم | сирота |
| claim (ownership claim) | klaim | クレーム | 클레임 | 认领 | مطالبة | заявка (клейм) |
| creator (vTual profile) | kreator | クリエイター | 크리에이터 | 创作者 | منشئ | автор |
| channel | channel | チャンネル | 채널 | 频道 | قناة | канал |
| activation (product) | aktivasi | アクティベーション | 활성화 | 激活 | تفعيل | активация |
| entitlement | hak akses produk | エンタイトルメント | 이용 권한 | 授权 | استحقاق | право доступа |
| invoice | invoice | 請求書 | 인보이스 | 发票 | فاتورة | счёт |
| payment gateway | gateway pembayaran | 決済ゲートウェイ | 결제 게이트웨이 | 支付网关 | بوابة الدفع | платёжный шлюз |
| settlement | settlement | 精算 | 정산 | 结算 | تسوية | расчёт (сеттлемент) |
| upload batch | batch unggahan | アップロードバッチ | 업로드 배치 | 上传批次 | دفعة الرفع | пакет загрузки |
| file hosting | file hosting | ファイルホスティング | 파일 호스팅 | 文件托管 | استضافة الملفات | файловый хостинг |
| persona | persona | ペルソナ | 페르소나 | 形象 | شخصية | персона |
| role / permission | peran / izin | ロール / 権限 | 역할 / 권한 | 角色 / 权限 | الدور / الصلاحيات | роль / разрешение |
| staff | staf | スタッフ | 스태프 | 工作人员 | فريق العمل | сотрудники |
| archive (verb) | arsipkan | アーカイブ | 보관 처리 | 归档 | أرشفة | архивировать |
| restore | pulihkan | 復元 | 복원 | 恢复 | استعادة | восстановить |
| connected accounts | akun terhubung | 連携アカウント | 연결된 계정 | 已连接账户 | الحسابات المتصلة | связанные аккаунты |
| two-factor authentication | autentikasi dua faktor | 二要素認証 | 2단계 인증 | 双重身份验证 | المصادقة الثنائية | двухфакторная аутентификация |
| notification | notifikasi | 通知 | 알림 | 通知 | إشعار | уведомление |
| save / cancel | simpan / batal | 保存 / キャンセル | 저장 / 취소 | 保存 / 取消 | حفظ / إلغاء | сохранить / отмена |
| loading | memuat… | 読み込み中… | 로드 중… | 加载中… | جارٍ التحميل… | загрузка… |
| trash / trashed | sampah / terhapus | ゴミ箱 / 削除済み | 휴지통 / 삭제됨 | 回收站 / 已删除 | المحذوفات | корзина / удалённые |
| subscription / plan | langganan / paket | サブスクリプション / プラン | 구독 / 요금제 | 订阅 / 方案 | اشتراك / خطة | подписка / тариф |
| identity (domain group) | identitas | アイデンティティ | 아이덴티티 | 身份 | الهوية | идентификация |
| wallet / e-wallet | dompet / dompet digital | ウォレット / 電子マネー | 지갑 / 전자지갑 | 钱包 / 电子钱包 | محفظة / محفظة إلكترونية | кошелёк / электронный кошелёк |

Register: id (Anda, formal netral), ja (です/ます), ko (합쇼체), zh-CN (您, simplified), ar (MSA formal), ru (вы, lowercase после «вы» kecuali awal kalimat). Use plain verb forms for buttons; full sentences for toasts/messages.

## Related

- [web/blade-admin.md](../web/blade-admin.md)
- [integration/vue-workers-spa.md](../integration/vue-workers-spa.md) — SPA JSON locales, runtime switching, fallback translation, hydration, `useI18n()` context rule, and Sweetalert2 chrome keys (`ui.swal.*`, `ui.dialog.*`)
- [domains/navigation/README.md](../domains/navigation/README.md) — Spatie menu fields
