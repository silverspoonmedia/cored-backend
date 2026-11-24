<?php

// Locale glossary scan — term decisions that must not drift back.
//
// `LocaleParityTest` compares locales structurally and `backend-locale-audit.php`
// compares keys, but neither can see a *word* choice. A settled term can silently
// regress — a later batch re-translates one key from the English source and
// reintroduces the rejected calque — and every structural guard still passes.
// That is how `ko` shipped `공개 설정` in ten backend values while the glossary
// already declared `공개 범위`.
//
// The per-locale maps below are the machine-readable half of the "Domain
// glossary" and "Glossary drift found while preparing the … wave" sections of
// `docs/platform/naming-conventions.md`. Add a rejected variant here in the same change
// that settles the term, so the decision is enforced instead of merely documented.
//
// Two checks per locale, both scanned over the backend `lang/{locale}/**/*.php`,
// `lang/{locale}.json`, and the sibling SPA `src/i18n/locales/{locale}.json`:
//
//   - forbidden: a literal substring that must never appear, because a better
//     term replaced it everywhere. Precise: zero false positives by construction.
//   - min_occurrences: a floor on how often a settled term appears per side. This
//     exists for the case a forbidden variant cannot express — `ko`'s `checkout`
//     collapse, where the rejected rendering (`결제`) is a correct word in general
//     and only wrong inside checkout copy, so it cannot be banned outright. The
//     floor is a "was this reverted?" tripwire, not a completeness proof: it fails
//     when a wave reverts many keys at once, which is the realistic regression.
//     Floors only ever need raising when keys are added, since added keys raise the
//     real count. They must be **re-measured and lowered in the same change that
//     deletes keys** — the 2026-09-24 sweep of 344 unreachable SPA keys
//     dropped every `spa` floor below its recorded value and turned this gate red
//     with 40 false "reverted?" findings. Run `--verbose`, confirm the terms are
//     present and the *drop* is explained by deleted keys rather than rewording, then
//     set the floor to the measured count. A floor left above the real count is a
//     permanently red gate, which is worse than no gate: it trains the reader to
//     ignore the output.
//
// Usage:
//   php scripts/locale-glossary-scan.php              all locales that have a map
//   php scripts/locale-glossary-scan.php ko ru        only these locales
//   php scripts/locale-glossary-scan.php --verbose    list every hit
//
// Exit code is 1 when any violation is found, so this doubles as a gate.
//
// The sibling repo is required: half the scan is the SPA pack, and the occurrence
// floors are counted over both sides. A run without `monolith-vue-workers` would
// silently drop half the violations and half every floor, so it must fail instead.
require __DIR__.'/lib/locale-scan-support.php';

$root = locale_scan_root();
$spaRoot = locale_scan_spa_root();
locale_scan_require_spa($spaRoot);

/**
 * Rejected variant => the settled term that replaced it.
 *
 * @var array<string, array<string, string>>
 */
const FORBIDDEN = [
    'ko' => [
        '공개 설정' => '공개 범위',
        '가시성' => '공개 범위',
        '마이크로스톡' => 'Microstock',
        '프로브' => '점검',
        '날비게이션' => '내비게이션',
        '인퍼런스' => '추론',
        '제공자' => '공급자',
        '하십시오' => '해요체 (-하세요)',
    ],
    'ru' => [
        // Calques and transliterations the wave replaced.
        'биллинг' => 'оплата',
        'зонд' => 'проверка',
        'Имперсонац' => 'Войти под пользователем',
        // `хэндл` → `хендл` used to sit here. Both forms are gone: `хендл` was the
        // `ru` rendering of the channel *handle*, which the 2026-10-07 product-domain
        // sunset deleted along with the domain, so the ban enforced a term the corpus
        // no longer has. The remaining `host` sense is `хостинг` (4 values) and was
        // never what this entry was about.
        'чекаут' => 'оформление заказа',
        // `checkout` and `billing` both used to render as `оплат`; the settled
        // split is checkout = `оформление заказа`, billing = `оплата`.
        'оформление оплаты' => 'оформление заказа',
        'Оплата заказа' => 'оформление заказа',
        // `workspace` is `рабочая область`; `рабочее пространство` is the calque.
        'рабочее пространство' => 'рабочая область',
        'рабочего пространства' => 'рабочей области',
        'рабочем пространстве' => 'рабочей области',
        'рабочим пространством' => 'рабочей областью',
        // `claim` (the ownership claim the vTual domain owned) is retired with it: the
        // 2026-10-07 sunset took the concept, its keys, and the `заявка` / `заявить
        // права` terms with it. Its three bans are gone rather than left behind,
        // because `претензи`/`заявление`/`претенд` are ordinary Russian words and a ban
        // with no corpus to justify it is a trap for the next unrelated key.
        // `management` (an entity with owners, members, and invites) is
        // `организация`. `менеджмент` is an uncountable collective noun in Russian
        // and was pluralized and declined into ungrammatical forms.
        'менеджмент' => 'организация',
        'креатор' => 'автор',
        // `session` is `сессия`; `сеанс` is the cinema/theatre sense.
        'сеанс' => 'сессия',
        // Russian product UI does not use the `Пожалуйста` filler.
        'Пожалуйста' => 'imperative alone (drop the filler)',
        // `service` is `Сервис`; `Служба` is the rejected synonym.
        'Служб' => 'Сервис',
        // `AI` renders as `ИИ` everywhere.
        'AI-' => 'ИИ-',
        ' AI ' => ' ИИ ',
        // `probe` renders as `Проверка`, `backlog` as `очередь`, `upstream` as the
        // storage noun, the payment pipeline as `шлюз`. (`handle` as `хендл` used to be
        // in this list; it left the corpus with the vTual domain on 2026-10-07.)
        'Test' => 'Проверка',
        'Origin' => 'Источник',
        'backlog' => 'очередь',
        'upstream' => 'хранилище',
        'ПШ' => 'шлюз',
        // `Session Has Expired` is `истёк`, not `устарела`.
        'устарела' => 'истёк',
        // The flat pack's `Admin` is the role noun, never the panel.
        'Панель администратора' => 'Администратор',
        // `payment channel` is `платёжный канал`; `канал оплаты` is drift from it.
        // `Способ оплаты` is deliberately *not* banned — the English key it renders
        // is literally `Payment method`.
        'канал оплаты' => 'платёжный канал',
        'каналы оплаты' => 'платёжные каналы',
        'каналов оплаты' => 'платёжных каналов',
    ],
    'id' => [
        // 403 `Forbidden` is `Terlarang`, not the literal `Dilarang`; 401
        // `Unauthorized` is `Tidak sah`, not the literal `Tidak Diizinkan`.
        'Dilarang' => 'Terlarang',
        'Tidak Diizinkan' => 'Tidak sah',
        // `expired` is `kedaluwarsa` (KBBI spelling), never the misspelling
        // `Kadaluwarsa`.
        'Kadaluwarsa' => 'kedaluwarsa',
        // `slug` is `Slug` on both sides; `Identitas URL` was the SPA-only calque.
        'Identitas URL' => 'Slug',
    ],
    'ja' => [
        // `claim` (the ownership claim the vTual domain owned) is retired with it. The
        // row was the glossary's one false friend — `クレーム` means "complaint" in
        // Japanese — and the ban existed only to keep the `申請` rendering from
        // collapsing. Both left with the domain on 2026-10-07; `クレーム` is no longer
        // banned, because a ban whose concept is gone only misleads the next wave.
        //
        // `点検` → `プローブ` used to sit here, and it is removed for the same reason
        // and one more: `プローブ` was the vTual rendering of *probe* (8 keys in
        // `lang/ja/api/vtual.php`, 1 in `web/settings.php`), so the replacement left
        // with the domain; and `点検` never occurred in a `ja` value at all, which made
        // the entry a `ko` word in the `ja` map. The surviving `probe` sentence is
        // `api/ai.connection.test_model_required`, which `ja` renders as `検査` — see
        // the corrected glossary row in `docs/platform/naming-conventions.md`.
    ],
    'zh_CN' => [
        // `slug` is `标识`; `子弹` ("bullet") was the SPA variant. `别名` is *not*
        // banned: `磁盘目标别名` is "disk target alias" and is correct.
        '子弹' => '标识',
        // `credential` is `凭证`, not `凭据`.
        '凭据' => '凭证',
        // `plan` is `方案`, not `套餐`.
        '套餐' => '方案',
        // `archive` (verb) is `归档`, not the noun `档案`.
        '档案' => '归档',
        // `log out` is `退出登录`, not `登出`.
        '登出' => '退出登录',
        // `show all` is `全部显示`, not `显示所有`.
        '显示所有' => '全部显示',
        // `like` (the vote) is `赞`, not `喜欢`, which reads as "be fond of".
        '喜欢' => '赞',
    ],
    'ar' => [
        // 410 `Gone` is `محتوى محذوف نهائيًا`, not the verb `ذهب` ("he went").
        'ذهب' => 'محتوى محذوف نهائيًا',
        // `slug` is `المعرّف`; `سبيكة` ("metal ingot") was the rejected variant.
        'سبيكة' => 'المعرّف',
        // Arabic orthography: the hamza-less `عذراً` was replaced by `عذرًا`.
        'عذراً' => 'عذرًا',
        // `redirect` is `إعادة توجيه`; the bare verbal noun `الإنتقال` — with a
        // wrong hamza seat — was dropped.
        'الإنتقال' => 'إعادة توجيه',
        // `hosting` took a plain alef: `الاستضافة`, not `الإستضافة`. Both spellings
        // are gone now — `الاستضافة` was the vTual *hosting channel*, and the
        // 2026-10-07 sunset deleted the only two values that used it. A ban whose
        // replacement has no corpus left enforces nothing and misleads the next wave,
        // so it is removed rather than kept. (The surviving `host` sense is the
        // Microstock store host, `مضيف`, which this entry never covered.)
        // `email` is `البريد الإلكتروني`, not the transliteration `الإيميل`.
        'الإيميل' => 'البريد الإلكتروني',
    ],
];

/**
 * Settled term => the least number of times it must appear, per side.
 *
 * These are the **monolith** floors: measured over the corpus that carries the
 * product domains. A consumer install (a boilerplate fork) legitimately drops
 * every product key, so the same absolute count is unreachable there and the
 * tripwire would fire on a corpus that never regressed.
 *
 * The per-install override is `scripts/locale-glossary-floors.json`, written by
 * `--dump-floors` and committed beside this script: an install states its own
 * measured counts instead of inheriting a number that describes someone else's
 * key set. A locale absent from that file keeps the floors below, so the monolith
 * (which ships no override) is unchanged.
 *
 * @var array<string, array<string, array{backend: int, spa: int}>>
 */
const MIN_OCCURRENCES = [
    'ko' => [
        '체크아웃' => ['backend' => 18, 'spa' => 28],
        '과금' => ['backend' => 11, 'spa' => 22],
    ],
    // `ru` has no single rejected word for most of its collapses — `оплата` is
    // correct outside checkout and `тариф` is correct outside the tier metric — so
    // these floors are the tripwire: they fail when a wave reverts many keys at
    // once. Stems are used on purpose so inflection (`оформлени` for
    // `оформление`/`оформления`) does not break the floor.
    'ru' => [
        // Lowered on 2026-10-07 (a product domain was sunset): the deleted
        // domain locale files carried the bulk of these terms on the backend
        // side and its SPA keys carried the bulk on the front. `заявк`,
        // `организац` and `хендл` left the corpus entirely — that domain was
        // their only home — so they are no longer floored. See the sunset
        // post-mortems in docs/archive/.
        'ИИ' => ['backend' => 47, 'spa' => 58],
        'оформлени' => ['backend' => 19, 'spa' => 20],
        'област' => ['backend' => 45, 'spa' => 65],
        'автор' => ['backend' => 6, 'spa' => 7],
        'проверк' => ['backend' => 6, 'spa' => 16],
        // `Session Has Expired` is `истёк`, not `устарела`; the *messages* keep that
        // verb while the expiry *column labels* settle on the noun phrase
        // `Действует до`, so both are floored. The two casings are floored
        // separately: `substr_count` is case-sensitive, and the original single
        // floor of 11 silently summed `истёк` + `Истёк`, so it could never be met.
        'истёк' => ['backend' => 7, 'spa' => 8],
        'Истёк' => ['backend' => 1, 'spa' => 1],
        'Действует до' => ['backend' => 2, 'spa' => 4],
        'Источник' => ['backend' => 4, 'spa' => 5],
        // Lowered 19 → 17 on 2026-11-19: the orphaned `web/identity-connections.php`
        // carried both `провайдер` hits (`provider_heading` and the generic
        // `provider_not_configured`, now an SPA-only pack). Measured count.
        'провайдер' => ['backend' => 17, 'spa' => 37],
    ],
    // `id` has no single rejected word for most of its collapses — `channel` is
    // correct outside a payment channel, `error` is the ordinary word for a system
    // error — so these floors are the tripwire: they fail when a wave reverts many
    // keys at once. Stems are used on purpose so inflection does not break the
    // floor.
    'id' => [
        'Terlarang' => ['backend' => 2, 'spa' => 1],
        // Lowered on 2026-10-07 (a product domain was sunset): the deleted
        // domain locale files carried the bulk of these terms on the backend
        // side and its SPA keys carried the bulk on the front. `klaim`,
        // `manajemen` and `kreator` left the corpus entirely — that domain was
        // their only home — so they are no longer floored. See the sunset
        // post-mortems in docs/archive/.
        'kedaluwarsa' => ['backend' => 11, 'spa' => 11],
        'Berkas' => ['backend' => 27, 'spa' => 24],
        // Lowered 14 → 12 on 2026-11-19: the orphaned `web/identity-connections.php`
        // carried both `verifikasi` hits (`verified`/`verify` on the connections
        // screen, now an SPA-only pack). Measured count after deletion.
        'verifikasi' => ['backend' => 12, 'spa' => 23],
        'kredensial' => ['backend' => 9, 'spa' => 12],
        // Lowered 23 → 18 on 2026-10-02 (a product domain was sunset): the five
        // `slug` SPA keys were the only `Slug` hits that went away. See the
        // sunset post-mortems in docs/archive/.
        'Slug' => ['backend' => 6, 'spa' => 10],
        'yatim' => ['backend' => 1, 'spa' => 1],
        'penagihan' => ['backend' => 13, 'spa' => 20],
    ],
    'ja' => [
        // Lowered on 2026-10-07 (a product domain was sunset): the deleted
        // domain locale files carried the bulk of these terms on the backend
        // side and its SPA keys carried the bulk on the front. `プローブ`,
        // `マネジメント`, `クリエイター`, `申請` and `オーファン` left the corpus
        // entirely — that domain was their only home — so they are no longer
        // floored. See the sunset post-mortems in docs/archive/.
        // Lowered 25 → 20 on 2026-10-02 (a product domain was sunset) and again
        // to the surviving 8/12 on 2026-10-07 (the second sunset).
        'スラッグ' => ['backend' => 8, 'spa' => 12],
        // 403 `Forbidden` is `アクセス禁止`, not the validation sentence
        // `…の入力は禁止されています。`, which is why the phrase cannot be banned —
        // the floor is the tripwire instead. Same for 302 `一時的な移動`: `発見` is
        // the ordinary word for "discovery" and is correct in the Discord keys.
        'アクセス禁止' => ['backend' => 2, 'spa' => 1],
        '一時的な移動' => ['backend' => 2, 'spa' => 1],
        'チェックアウト' => ['backend' => 20, 'spa' => 25],
        '課金' => ['backend' => 9, 'spa' => 12],
        '認証情報' => ['backend' => 13, 'spa' => 22],
    ],
    'zh_CN' => [
        // Lowered on 2026-10-07 (a product domain was sunset): the deleted
        // domain locale files carried the bulk of these terms on the backend
        // side and its SPA keys carried the bulk on the front. `管理机构` and
        // `认领` left the corpus entirely — that domain was their only home —
        // so they are no longer floored. See the sunset post-mortems in
        // docs/archive/.
        '标识' => ['backend' => 8, 'spa' => 14],
        '结账' => ['backend' => 21, 'spa' => 31],
        '计费' => ['backend' => 14, 'spa' => 27],
        '归档' => ['backend' => 7, 'spa' => 8],
        '凭证' => ['backend' => 15, 'spa' => 24],
        '孤立' => ['backend' => 1, 'spa' => 1],
        // Lowered 39 → 36 on 2026-11-19: two `验证` hits lived in the orphaned
        // `web/identity-connections.php` and one in `menu.php`
        // (`two_factor` = `双重身份验证`), both Blade-era groups deleted with this
        // change. The remaining 36 are live; the floor is the measured count.
        '验证' => ['backend' => 36, 'spa' => 81],
        '创作者' => ['backend' => 1, 'spa' => 1],
        '方案' => ['backend' => 24, 'spa' => 30],
        '登录' => ['backend' => 22, 'spa' => 33],
        '退出登录' => ['backend' => 6, 'spa' => 7],
    ],
    'ar' => [
        // Lowered on 2026-10-07 (a product domain was sunset): the deleted
        // domain locale files carried most of these terms on the backend side
        // and its SPA keys carried most on the front. `مطالبة` left the corpus
        // entirely and `فحص` survives only on the backend, so neither keeps a
        // floor on the side that emptied. See the sunset post-mortems in
        // docs/archive/.
        'المعرّف' => ['backend' => 1, 'spa' => 7],
        'فحص' => ['backend' => 1],
        'إتمام الشراء' => ['backend' => 16, 'spa' => 23],
        'الفوترة' => ['backend' => 5, 'spa' => 15],
        'أرشفة' => ['backend' => 5, 'spa' => 9],
        // Lowered 15 → 14 on 2026-11-19: the orphaned `notifications.php`
        // (Blade-era flash messages) held `restored` `تمت استعادة السجل بنجاح.`,
        // the one occurrence this floor was counting. The floor is the measured
        // count after that deletion, not a reverted wave.
        'استعادة' => ['backend' => 14, 'spa' => 22],
        'بيانات الاعتماد' => ['backend' => 10, 'spa' => 14],
        'قائمة الانتظار' => ['backend' => 7, 'spa' => 4],
        'التخزين' => ['backend' => 13, 'spa' => 21],
        'يتيم' => ['backend' => 1, 'spa' => 1],
        'منشئ' => ['backend' => 1, 'spa' => 1],
        'خطة' => ['backend' => 16, 'spa' => 20],
        // The glossary row used to say `تحقّق` (with a shadda) and the corpus said
        // `تحقق`; the corpus is right and the row was corrected.
        'تحقق' => ['backend' => 18, 'spa' => 30],
    ],
];

[$requested, $flags] = locale_scan_args($argv);
$verbose = in_array('--verbose', $flags, true);
$dumpFloors = in_array('--dump-floors', $flags, true);

$locales = array_values(array_intersect(array_keys(FORBIDDEN + MIN_OCCURRENCES), locale_scan_supported()));
sort($locales);

$locales = locale_scan_select($locales, $requested, 'locale with a glossary map');

/**
 * This install's floors, layered over the monolith's.
 *
 * A boilerplate fork carries no product keys, so an absolute count measured over
 * the monolith corpus is unreachable there. The override file states the fork's
 * own measured numbers; a locale it does not mention keeps the monolith floor, so
 * an install that ships the full corpus needs no override at all.
 *
 * @return array<string, array<string, array{backend: int, spa: int}>>
 */
function install_floors(): array
{
    $path = locale_scan_root().'/scripts/locale-glossary-floors.json';

    if (! is_file($path)) {
        return MIN_OCCURRENCES;
    }

    $data = json_decode((string) file_get_contents($path), true);

    if (! is_array($data)) {
        fwrite(STDERR, 'locale-glossary-floors.json is not valid JSON; refusing to scan with an unreadable override.'.PHP_EOL);

        exit(1);
    }

    return array_replace_recursive(MIN_OCCURRENCES, $data);
}

/**
 * Flat dotted path => value, for both PHP lang arrays and JSON packs.
 *
 * @return array<string, string>
 */
function flatten(array $data, string $prefix = ''): array
{
    $out = [];

    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $out += flatten($value, $path);
        } elseif (is_string($value)) {
            $out[$path] = $value;
        }
    }

    return $out;
}

/**
 * Every scalar entry of one locale as `[source, dottedKey, value]`, plus the two
 * concatenated blobs the occurrence floors are counted against.
 *
 * @return array{0: array<int, array{0: string, 1: string, 2: string}>, 1: array<string, string>}
 */
function collect(string $root, string $spaRoot, string $locale): array
{
    $rows = [];
    $blobs = ['backend' => '', 'spa' => ''];
    $seenSpa = [];
    $dir = $root.'/lang/'.$locale;

    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            $namespace = str_replace('/', '.', substr($relative, 0, -4));
            $data = include $file->getPathname();

            if (! is_array($data)) {
                continue;
            }

            foreach (flatten($data) as $key => $value) {
                $rows[] = ['lang/'.$locale.'/'.$relative, $namespace.'.'.$key, $value];
                $blobs['backend'] .= $value."\n";
            }
        }
    }

    // Packaged core domains keep their translations in
    // packages/*/resources/lang/{locale} with the same un-namespaced keys.
    // Occurrence floors count the backend blob, so skipping these trees would
    // report a settled term as "reverted" the moment its file moved.
    foreach (glob($root.'/packages/*/resources/lang/'.$locale, GLOB_ONLYDIR) ?: [] as $packageLocale) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packageLocale, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($packageLocale) + 1));
            $namespace = str_replace('/', '.', substr($relative, 0, -4));
            $data = include $file->getPathname();

            if (! is_array($data)) {
                continue;
            }

            $owner = str_replace($root.'/', '', str_replace('\\', '/', $packageLocale));

            foreach (flatten($data) as $key => $value) {
                $rows[] = [$owner.'/'.$relative, $namespace.'.'.$key, $value];
                $blobs['backend'] .= $value."\n";
            }
        }
    }

    $pack = $root.'/lang/'.$locale.'.json';

    if (is_file($pack)) {
        $raw = (string) file_get_contents($pack);
        $blobs['backend'] .= $raw."\n";

        foreach (flatten((array) json_decode($raw, true)) as $key => $value) {
            $rows[] = ['lang/'.$locale.'.json', $key, $value];
        }
    }

    // The SPA splits its copy: core keys live in `locales/`, a product's keys in
    // `features/{name}/`. Both are user-visible strings, so both are scanned —
    // a floor measured on the merged pack would go red the moment a feature moves.
    $spaFiles = array_merge(
        glob($spaRoot.'/src/i18n/locales/*.json') === false ? [] : glob($spaRoot.'/src/i18n/locales/*.json'),
        glob($spaRoot.'/src/i18n/features/*/*.json') === false ? [] : glob($spaRoot.'/src/i18n/features/*/*.json'),
    );

    foreach ($spaFiles as $spaFile) {
        if (basename($spaFile, '.json') !== str_replace('zh_CN', 'zh-CN', $locale)) {
            continue;
        }

        $unique = 'src/i18n/'.(str_contains($spaFile, '/features/') ? 'features/'.basename(dirname($spaFile)).'/' : 'locales/').basename($spaFile);

        if (in_array($unique, $seenSpa, true)) {
            continue;
        }

        $seenSpa[] = $unique;
        $raw = (string) file_get_contents($spaFile);
        $blobs['spa'] .= $raw."\n";

        foreach (flatten((array) json_decode($raw, true)) as $key => $value) {
            $rows[] = ['spa:'.$unique, $key, $value];
        }
    }

    return [$rows, $blobs];
}

$violations = [];
$blind = [];
$measured = [];
$floorsByLocale = install_floors();

foreach ($locales as $locale) {
    $forbidden = FORBIDDEN[$locale] ?? [];
    $floors = $floorsByLocale[$locale] ?? [];

    // The dump records what this corpus carries, so it must count every baseline
    // term — reading the override back would only echo the numbers it wrote.
    $measuredTerms = array_unique(array_merge(
        array_keys($floors),
        array_keys(MIN_OCCURRENCES[$locale] ?? []),
    ));
    // A locale whose surfaces are absent scans as "0 violations" because there is
    // nothing to read. Say so instead of reporting success.
    $absent = array_keys(array_filter(
        locale_scan_surfaces($root, $spaRoot, $locale),
        static fn (string $path): bool => ! is_file($path),
    ));

    if ($absent !== []) {
        $blind[] = $locale.' ('.implode(', ', $absent).')';
        echo str_pad($locale, 8), 'cannot read: ', implode(', ', $absent), PHP_EOL;

        continue;
    }

    [$rows, $blobs] = collect($root, $spaRoot, $locale);
    $found = 0;

    foreach ($rows as [$file, $key, $value]) {
        foreach ($forbidden as $variant => $settled) {
            if (! str_contains($value, $variant)) {
                continue;
            }

            $found++;
            $violations[] = [$locale, $file, $key, $value, "forbidden `{$variant}` — use `{$settled}`"];
        }
    }

    foreach ($measuredTerms as $term) {
        $actualBackend = substr_count($blobs['backend'], $term);
        $actualSpa = substr_count($blobs['spa'], $term);

        $measured[$locale][$term] = ['backend' => $actualBackend, 'spa' => $actualSpa];

        foreach ($floors[$term] ?? [] as $side => $required) {
            $actual = $side === 'backend' ? $actualBackend : $actualSpa;

            // Zero is the override's way of saying "this term left this install's
            // corpus" (a product-domain term whose only keys were removed). A floor
            // of zero asserts nothing, so it is skipped rather than always green.
            if ($required === 0 || $actual >= $required) {
                continue;
            }

            $found++;
            $violations[] = [$locale, $side, $term, (string) $actual, "settled term appears {$actual}× (floor {$required}) — reverted?"];
        }
    }

    echo str_pad($locale, 8), str_pad((string) count($rows), 8), 'entries  ',
    ($found === 0 ? 'ok' : $found.' violation(s)'), PHP_EOL;

    if ($verbose) {
        foreach ($measuredTerms as $term) {
            echo '  ', $term, '  backend=', substr_count($blobs['backend'], $term), ' spa=', substr_count($blobs['spa'], $term), PHP_EOL;
        }
    }
}

echo PHP_EOL, 'total violations: ', count($violations), PHP_EOL;

if ($dumpFloors) {
    // Only the deltas: a side whose measured count still meets the inherited floor
    // keeps that floor, so this file stays a short list of "what this corpus really
    // carries" instead of a frozen copy of every number. A side measured at zero is
    // written as zero and skipped by the scan — the term left this installation's
    // corpus, and a floor of zero would assert nothing.
    $deltas = [];

    foreach ($measured as $locale => $terms) {
        foreach ($terms as $term => $sides) {
            foreach ($sides as $side => $actual) {
                $inherited = MIN_OCCURRENCES[$locale][$term][$side] ?? 0;

                if ($actual < $inherited) {
                    $deltas[$locale][$term][$side] = $actual;
                }
            }
        }
    }

    $path = locale_scan_root().'/scripts/locale-glossary-floors.json';

    file_put_contents(
        $path,
        json_encode($deltas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
    );

    echo 'wrote ', count($deltas), ' locale override(s) to ', $path, PHP_EOL;

    exit(0);
}

if ($blind !== []) {
    fwrite(STDERR, 'cannot read the surfaces of: '.implode('; ', $blind).PHP_EOL);
    fwrite(STDERR, 'A locale that cannot be read scans as clean, so this is a failure, not a skip.'.PHP_EOL);
    exit(1);
}

if (! $verbose && $violations !== []) {
    foreach ($violations as [$locale, $file, $key, $value, $reason]) {
        echo PHP_EOL, $locale, '  ', $file, '  ', $key, PHP_EOL, '    ', $value, PHP_EOL, '    → ', $reason, PHP_EOL;
    }
}

exit($violations === [] ? 0 : 1);
