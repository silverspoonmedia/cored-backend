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
//     deletes keys** — the 2026-09-24 sweep of 344 unreachable `web.vtual*` SPA keys
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
        'хэндл' => 'хендл',
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
        // `claim` is `заявка`; `претензия`/`заявление` are the wrong nouns, and the
        // verb is `заявить права`, never `претендовать`.
        'претензи' => 'заявка',
        'заявление' => 'заявка',
        'претенд' => 'заявить права',
        // `management` (the vTual entity with owners, members, and invites) is
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
        // storage noun, `handle` as `хендл`, the payment pipeline as `шлюз`.
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
        // `claim` is `申請`; `クレーム` is a false friend — it means "complaint" in
        // Japanese, which is why the wave never used it despite the glossary row.
        'クレーム' => '申請',
        // `probe` transliterates as `プローブ`; `点検` is the `ko` word for it.
        '点検' => 'プローブ',
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
        // `hosting` takes a plain alef: `الاستضافة`, not `الإستضافة`.
        'الإستضافة' => 'الاستضافة',
        // `email` is `البريد الإلكتروني`, not the transliteration `الإيميل`.
        'الإيميل' => 'البريد الإلكتروني',
    ],
];

/**
 * Settled term => the least number of times it must appear, per side.
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
    // once. Stems are used on purpose so inflection (`заявка`/`заявки`/`заявок`)
    // does not break the floor.
    'ru' => [
        'ИИ' => ['backend' => 54, 'spa' => 65],
        'оформлени' => ['backend' => 19, 'spa' => 20],
        'област' => ['backend' => 45, 'spa' => 65],
        'заявк' => ['backend' => 56, 'spa' => 55],
        'организац' => ['backend' => 21, 'spa' => 32],
        'автор' => ['backend' => 14, 'spa' => 24],
        'хендл' => ['backend' => 5, 'spa' => 7],
        'проверк' => ['backend' => 43, 'spa' => 56],
        // `Session Has Expired` is `истёк`, not `устарела`; the *messages* keep that
        // verb while the expiry *column labels* settle on the noun phrase
        // `Действует до`, so both are floored. The two casings are floored
        // separately: `substr_count` is case-sensitive, and the original single
        // floor of 11 silently summed `истёк` + `Истёк`, so it could never be met.
        'истёк' => ['backend' => 10, 'spa' => 10],
        'Истёк' => ['backend' => 1, 'spa' => 1],
        'Действует до' => ['backend' => 2, 'spa' => 5],
        'Источник' => ['backend' => 5, 'spa' => 6],
        'провайдер' => ['backend' => 19, 'spa' => 37],
    ],
    // `id` has no single rejected word for most of its collapses — `channel` is
    // correct outside a payment channel, `error` is the ordinary word for a system
    // error — so these floors are the tripwire: they fail when a wave reverts many
    // keys at once. Stems are used on purpose so inflection does not break the
    // floor.
    'id' => [
        'Terlarang' => ['backend' => 2, 'spa' => 1],
        'kedaluwarsa' => ['backend' => 16, 'spa' => 14],
        'Berkas' => ['backend' => 27, 'spa' => 24],
        'klaim' => ['backend' => 54, 'spa' => 57],
        'verifikasi' => ['backend' => 51, 'spa' => 59],
        'kredensial' => ['backend' => 28, 'spa' => 39],
        // The loanwords that replaced the English words left in prose.
        'manajemen' => ['backend' => 21, 'spa' => 31],
        'kreator' => ['backend' => 10, 'spa' => 23],
        // Lowered 23 → 18 on 2026-10-02 (Blog domain removed): the five
        // `web.blog.*.slug` SPA keys were the only `Slug` hits that went away.
        'Slug' => ['backend' => 11, 'spa' => 18],
        'yatim' => ['backend' => 13, 'spa' => 21],
        'penagihan' => ['backend' => 13, 'spa' => 20],
    ],
    'ja' => [
        'プローブ' => ['backend' => 9, 'spa' => 14],
        // Lowered 25 → 20 on 2026-10-02 (Blog domain removed): the five
        // `web.blog.*.slug` SPA keys were the only `スラッグ` hits that went away.
        'スラッグ' => ['backend' => 13, 'spa' => 20],
        // 403 `Forbidden` is `アクセス禁止`, not the validation sentence
        // `…の入力は禁止されています。`, which is why the phrase cannot be banned —
        // the floor is the tripwire instead. Same for 302 `一時的な移動`: `発見` is
        // the ordinary word for "discovery" and is correct in the Discord keys.
        'アクセス禁止' => ['backend' => 2, 'spa' => 1],
        '一時的な移動' => ['backend' => 2, 'spa' => 1],
        'チェックアウト' => ['backend' => 20, 'spa' => 25],
        '課金' => ['backend' => 9, 'spa' => 12],
        '認証情報' => ['backend' => 41, 'spa' => 66],
        'マネジメント' => ['backend' => 25, 'spa' => 39],
        'クリエイター' => ['backend' => 19, 'spa' => 37],
        '申請' => ['backend' => 84, 'spa' => 96],
        'オーファン' => ['backend' => 6, 'spa' => 9],
    ],
    'zh_CN' => [
        // Lowered 27 → 22 on 2026-10-02 (Blog domain removed): the five
        // `web.blog.*.slug` SPA keys were the only `标识` hits that went away.
        '标识' => ['backend' => 13, 'spa' => 22],
        '结账' => ['backend' => 21, 'spa' => 31],
        '计费' => ['backend' => 14, 'spa' => 27],
        '归档' => ['backend' => 26, 'spa' => 31],
        '凭证' => ['backend' => 47, 'spa' => 70],
        '管理机构' => ['backend' => 26, 'spa' => 40],
        '孤立' => ['backend' => 16, 'spa' => 21],
        '认领' => ['backend' => 80, 'spa' => 94],
        '验证' => ['backend' => 93, 'spa' => 139],
        '创作者' => ['backend' => 20, 'spa' => 41],
        '方案' => ['backend' => 24, 'spa' => 30],
        '登录' => ['backend' => 22, 'spa' => 43],
        '退出登录' => ['backend' => 6, 'spa' => 7],
    ],
    'ar' => [
        // Lowered 25 → 20 and 28 → 27 on 2026-10-02 (Blog domain removed): the
        // five `web.blog.*` SPA keys carried the only `المعرّف` hits, and the
        // deleted `lang/ar/api/blog.php` carried one `استعادة`.
        'المعرّف' => ['backend' => 8, 'spa' => 20],
        'فحص' => ['backend' => 4, 'spa' => 8],
        'إتمام الشراء' => ['backend' => 16, 'spa' => 23],
        'الفوترة' => ['backend' => 5, 'spa' => 15],
        'أرشفة' => ['backend' => 14, 'spa' => 21],
        'استعادة' => ['backend' => 16, 'spa' => 27],
        'بيانات الاعتماد' => ['backend' => 29, 'spa' => 43],
        'قائمة الانتظار' => ['backend' => 15, 'spa' => 10],
        'التخزين' => ['backend' => 13, 'spa' => 21],
        'يتيم' => ['backend' => 13, 'spa' => 18],
        'مطالبة' => ['backend' => 65, 'spa' => 72],
        'منشئ' => ['backend' => 20, 'spa' => 38],
        'خطة' => ['backend' => 16, 'spa' => 20],
        // The glossary row used to say `تحقّق` (with a shadda) and the corpus said
        // `تحقق`; the corpus is right and the row was corrected.
        'تحقق' => ['backend' => 68, 'spa' => 85],
    ],
];

[$requested, $flags] = locale_scan_args($argv);
$verbose = in_array('--verbose', $flags, true);

$locales = array_keys(FORBIDDEN + MIN_OCCURRENCES);
sort($locales);

$locales = locale_scan_select($locales, $requested, 'locale with a glossary map');

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

    $pack = $root.'/lang/'.$locale.'.json';

    if (is_file($pack)) {
        $raw = (string) file_get_contents($pack);
        $blobs['backend'] .= $raw."\n";

        foreach (flatten((array) json_decode($raw, true)) as $key => $value) {
            $rows[] = ['lang/'.$locale.'.json', $key, $value];
        }
    }

    $spaFile = $spaRoot.'/src/i18n/locales/'.str_replace('zh_CN', 'zh-CN', $locale).'.json';

    if (is_file($spaFile)) {
        $raw = (string) file_get_contents($spaFile);
        $blobs['spa'] .= $raw."\n";

        foreach (flatten((array) json_decode($raw, true)) as $key => $value) {
            $rows[] = ['spa:src/i18n/locales/'.basename($spaFile), $key, $value];
        }
    }

    return [$rows, $blobs];
}

$violations = [];
$blind = [];

foreach ($locales as $locale) {
    $forbidden = FORBIDDEN[$locale] ?? [];
    $floors = MIN_OCCURRENCES[$locale] ?? [];

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

    foreach ($floors as $term => $minimum) {
        foreach ($minimum as $side => $required) {
            $actual = substr_count($blobs[$side], $term);

            if ($actual >= $required) {
                continue;
            }

            $found++;
            $violations[] = [$locale, $side, $term, (string) $actual, "settled term appears {$actual}× (floor {$required}) — reverted?"];
        }
    }

    echo str_pad($locale, 8), str_pad((string) count($rows), 8), 'entries  ',
    ($found === 0 ? 'ok' : $found.' violation(s)'), PHP_EOL;

    if ($verbose) {
        foreach ($floors as $term => $minimum) {
            echo '  ', $term, '  backend=', substr_count($blobs['backend'], $term), ' spa=', substr_count($blobs['spa'], $term), PHP_EOL;
        }
    }
}

echo PHP_EOL, 'total violations: ', count($violations), PHP_EOL;

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
