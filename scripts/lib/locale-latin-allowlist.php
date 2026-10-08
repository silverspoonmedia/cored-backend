<?php

/**
 * Latin-token policy for `scripts/locale-prose-scan.php`.
 *
 * The scan answers one question per value: does a locale that should not be writing
 * English still carry English? Four lists decide the answer.
 *
 *   - `machine_patterns` — regexes removed from the value before any token is
 *     extracted. This is where identifiers, paths, commands, domains, and code
 *     examples live, so the token lists stay readable.
 *   - `machine` — single tokens that are correct in every locale because they are
 *     not copy: brands, protocols, formats, acronyms, enum values the API itself
 *     defines (`pending`, `live`, `public`), and named runtime components.
 *   - `glossary` — the Domain glossary table from `docs/platform/naming-conventions.md`,
 *     machine-readable. For a Latin-script target (`id`) it decides *which* English
 *     words are watched: a term whose settled `id` rendering is the same word
 *     (`workspace`, `channel`, `checkout`, `invoice`) is accepted, and one whose
 *     rendering differs (`management` → `manajemen`, `role` → `peran`) is watched.
 *   - `accepted` — per-locale extras, each with the reason it is allowed. An entry
 *     here is a reviewed decision, not a suppression: add one only after reading the
 *     value against its consuming surface.
 *
 * Keep this file in step with the glossary. A term settled in the doc and missing
 * here means the scan cannot see it.
 *
 * @return array{machine_patterns: array<int, string>, machine: array<int, string>, glossary: array<string, array<string, string>>, accepted: array<string, array<string, string>>}
 */

// Content that is never prose. Order matters: the more specific patterns run first.
$machinePatterns = [
    '/\bphp\s+artisan\s+\S+/i',                       // artisan command invocations
    '/\b(?:GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)\s+\/\S+/i', // documented HTTP calls
    '#\bhttps?://\S+#i',                              // absolute URLs
    '#\b[\w-]+\.(?:com|tv|org|net|io|dev)\b\S*#i',    // bare domains, e.g. youtube.com/…
    '/\b[A-Za-z]+\/[A-Za-z_]+\b/',                    // IANA timezone identifiers, Asia/Jakarta
    '/\b[A-Z][a-z0-9]+(?:[A-Z][a-z0-9]+)+\b/',        // PascalCase class names
    '/\b[a-z][a-z0-9]*(?:[A-Z][a-z0-9]+)+\b/',        // camelCase members
    '/\b[A-Za-z][A-Za-z0-9]*_[A-Za-z0-9_]+\b/',       // snake_case fields and params
    '/\b[A-Za-z_][A-Za-z0-9_]*=[A-Za-z0-9_.-]+/',     // key=value literals
    '/["\x27][A-Za-z0-9_.-]+["\x27]/',                // quoted enum literals
    '/\[[A-Za-z]+\]/',                                // regex character classes in examples
    '/--[a-z][a-z0-9-]*/i',                           // CLI flags
    '/\b[\w.+-]+@[\w-]+\.[\w.]+/',                    // e-mail addresses
    '/\b[A-Za-z]{2,5}-[A-Za-z0-9-]+\b/',              // hyphenated tokens: h-captcha, path-style, flat-lay
    '/:([A-Za-z_][A-Za-z0-9_]*)/',                    // Laravel placeholders
    '/\{[A-Za-z_][A-Za-z0-9_.\[\]\x27"]*\}/',         // vue-i18n placeholders
    '/&\w+;|&#\d+;/',                                 // HTML entities
    '/\b\d{4}-\d{2}-\d{2}\b/',                        // dates in examples
    '/\[[A-Za-z]{2,6}\]/',                            // bracketed tokens: [OI], [TODO]
];

$machine = [
    // Product and platform brands — never translated, per the glossary preamble.
    'Archivd', 'Microstock', 'Discord', 'YouTube', 'Twitch', 'Kick',
    'QRIS', 'iPaymu', 'Midtrans', 'Xendit', 'Doku', 'GoPay', 'OVO', 'Dana', 'ShopeePay',
    'LinkAja', 'Stripe', 'Snap', 'hCaptcha', 'reCAPTCHA', 'Railgun', 'FFmpeg', 'Monolith',
    'Shutterstock', 'Adobe', 'Getty', 'iStock', 'Dreamstime', 'Vecteezy', 'Freepik',
    'Depositphotos', 'Frankfurter', 'Pusher', 'WebSub', 'Laravel', 'Redis', 'Slack', 'Sentry',
    'Vault', 'Bootstrap', 'Icons', 'Router',
    // Example IDs a user has to replace with their own value.
    'abc123', 'def456',
    // Midtrans API surface names.
    'Open', 'Core',
    // HTTP methods. A user has to send exactly this verb, so it is not translated.
    'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS',
    // Product names and add-on labels.
    'Team Workspace Slot',
    // Protocol, format, and machine-contract vocabulary.
    'API', 'HTTP', 'HTTPS', 'URL', 'URI', 'UUID', 'ULID', 'UID', 'ID', 'CSRF', 'JWT', 'HTML',
    'JSON', 'SQL', 'CSP', 'MIME', 'WebSocket', 'WebSockets', 'AI', 'UI', 'UX', 'CMS', 'SEO',
    'CDN', 'DNS', 'IP', 'IPv4', 'IPv6', 'MAC', 'USD', 'IDR', 'IANA', 'UTC', 'GMT', 'ISO',
    'RFC', 'WWW', 'FAQ', 'KYC', 'AML', 'VAT', 'NIB', 'PDF', 'CSV', 'XLSX', 'ZIP', 'PNG',
    'JPEG', 'WebP', 'GIF', 'SVG', 'ICO', 'AVIF', 'MP4', 'WebM', 'HLS', 'RTMP', 'HMAC', 'AES',
    'RSA', 'TLS', 'SSL', 'TTL', 'DMCA', 'S3', 'R2', 'OAuth', 'Bearer', 'Helix', 'PHP', 'Vue',
    'SPA', 'QR', 'PG', 'VA', 'TOTP', 'MVP', 'HEX', 'SHA', 'YAML', 'yaml',
    'sha256', 'SIGNATURE', 'env', 'regex', 'enum', 'cron', 'webhook', 'Webhook', 'px',
    'Data', 'OpenAI',
    // AI model and mode names.
    'gpt', 'vision', 'Thinking', 'chat', 'completions',
    // Enum values and field literals the API itself defines. They are contract text,
    // not copy: the client sends and receives these exact strings.
    'pending', 'challenge', 'existing', 'new', 'true', 'false', 'before', 'after',
    'cascade', 'position', 'parent', 'reference', 'type', 'order', 'path', 'style',
    'price', 'length', 'content', 'part', 'parts', 'slot', 'team', 'root', 'admin',
    'client', 'oauth', 'key', 'app', 'live', 'completed', 'public',
    'private', 'Free', 'Paid', 'section', 'route',
    // Values a user has to locate in an external platform's own UI, or that name an
    // internal role/permission/component by its identifier.
    'login', 'Enter', 'email', 'Email', 'From', 'Slug', 'cookie', 'Cookie',
    'Web', 'Beta', 'owner', 'initiate', 'payment',
    'service', 'house', 'dashboard', 'abc', 'def', 'upstream',
    'multipart', 'presigned', 'captcha', 'Captcha', 'hash',
    'broadcast', 'realtime', 'token',
    // External platform field names a user must locate in that platform's own UI.
    'About',
    // Payment route kinds, taken from `Routing_Price.yaml` and used as literals.
    'primary', 'only', 'fallback',
];

// Domain glossary — `docs/platform/naming-conventions.md#domain-glossary`. One row per
// concept; the key is the English term the scan watches for in `id` mode.
$glossary = [
    'workspace' => ['id' => 'workspace', 'ja' => 'ワークスペース', 'ko' => '워크스페이스', 'zh_CN' => '工作区', 'ar' => 'مساحة العمل', 'ru' => 'рабочая область'],
    'orphan' => ['id' => 'yatim', 'ja' => 'オーファン', 'ko' => '고아', 'zh_CN' => '孤立', 'ar' => 'يتيم', 'ru' => 'сирота'],
    'verify' => ['id' => 'verifikasi', 'ja' => '検証', 'ko' => '검증', 'zh_CN' => '验证', 'ar' => 'تحقق', 'ru' => 'проверка'],
    'creator' => ['id' => 'kreator', 'ja' => 'クリエイター', 'ko' => '크리에이터', 'zh_CN' => '创作者', 'ar' => 'منشئ', 'ru' => 'автор'],
    'channel' => ['id' => 'channel', 'ja' => 'チャンネル', 'ko' => '채널', 'zh_CN' => '渠道', 'ar' => 'قناة', 'ru' => 'канал'],
    'slug' => ['id' => 'Slug', 'ja' => 'スラッグ', 'ko' => '슬러그', 'zh_CN' => '标识', 'ar' => 'المعرّف', 'ru' => 'Слаг'],
    'activation' => ['id' => 'aktivasi', 'ja' => 'アクティベーション', 'ko' => '활성화', 'zh_CN' => '激活', 'ar' => 'تفعيل', 'ru' => 'активация'],
    'entitlement' => ['id' => 'hak akses produk', 'ja' => 'エンタイトルメント', 'ko' => '이용 권한', 'zh_CN' => '授权', 'ar' => 'استحقاق', 'ru' => 'право доступа'],
    'invoice' => ['id' => 'invoice', 'ja' => '請求書', 'ko' => '인보이스', 'zh_CN' => '发票', 'ar' => 'فاتورة', 'ru' => 'счёт'],
    'gateway' => ['id' => 'gateway', 'ja' => 'ゲートウェイ', 'ko' => '게이트웨이', 'zh_CN' => '网关', 'ar' => 'بوابة', 'ru' => 'шлюз'],
    'settlement' => ['id' => 'settlement', 'ja' => '精算', 'ko' => '정산', 'zh_CN' => '结算', 'ar' => 'تسوية', 'ru' => 'расчёт'],
    'batch' => ['id' => 'batch', 'ja' => 'バッチ', 'ko' => '배치', 'zh_CN' => '批次', 'ar' => 'دفعة', 'ru' => 'пакет'],
    'role' => ['id' => 'peran', 'ja' => 'ロール', 'ko' => '역할', 'zh_CN' => '角色', 'ar' => 'الدور', 'ru' => 'роль'],
    'permission' => ['id' => 'izin', 'ja' => '権限', 'ko' => '권한', 'zh_CN' => '权限', 'ar' => 'الصلاحيات', 'ru' => 'разрешение'],
    'staff' => ['id' => 'staf', 'ja' => 'スタッフ', 'ko' => '스태프', 'zh_CN' => '工作人员', 'ar' => 'الموظفون', 'ru' => 'сотрудники'],
    'restore' => ['id' => 'pulihkan', 'ja' => '復元', 'ko' => '복원', 'zh_CN' => '恢复', 'ar' => 'استعادة', 'ru' => 'восстановить'],
    'notification' => ['id' => 'notifikasi', 'ja' => '通知', 'ko' => '알림', 'zh_CN' => '通知', 'ar' => 'إشعار', 'ru' => 'уведомление'],
    'loading' => ['id' => 'memuat', 'ja' => '読み込み中', 'ko' => '로드 중', 'zh_CN' => '加载中', 'ar' => 'جارٍ التحميل', 'ru' => 'загрузка'],
    'trash' => ['id' => 'sampah', 'ja' => 'ゴミ箱', 'ko' => '휴지통', 'zh_CN' => '回收站', 'ar' => 'سلة المهملات', 'ru' => 'корзина'],
    'subscription' => ['id' => 'langganan', 'ja' => 'サブスクリプション', 'ko' => '구독', 'zh_CN' => '订阅', 'ar' => 'اشتراك', 'ru' => 'подписка'],
    'plan' => ['id' => 'paket', 'ja' => 'プラン', 'ko' => '요금제', 'zh_CN' => '方案', 'ar' => 'خطة', 'ru' => 'тариф'],
    'credential' => ['id' => 'kredensial', 'ja' => '認証情報', 'ko' => '자격 증명', 'zh_CN' => '凭证', 'ar' => 'بيانات الاعتماد', 'ru' => 'учётные данные'],
    'identity' => ['id' => 'identitas', 'ja' => 'アイデンティティ', 'ko' => '아이덴티티', 'zh_CN' => '身份', 'ar' => 'الهوية', 'ru' => 'идентификация'],
    'wallet' => ['id' => 'dompet', 'ja' => 'ウォレット', 'ko' => '지갑', 'zh_CN' => '钱包', 'ar' => 'محفظة', 'ru' => 'кошелёк'],
    'checkout' => ['id' => 'checkout', 'ja' => 'チェックアウト', 'ko' => '체크아웃', 'zh_CN' => '结账', 'ar' => 'إتمام الشراء', 'ru' => 'оформление заказа'],
    'billing' => ['id' => 'penagihan', 'ja' => '課金', 'ko' => '과금', 'zh_CN' => '计费', 'ar' => 'الفوترة', 'ru' => 'оплата'],
    'probe' => ['id' => 'probe', 'ja' => 'プローブ', 'ko' => '점검', 'zh_CN' => '探测', 'ar' => 'فحص', 'ru' => 'проверка'],
    'management' => ['id' => 'manajemen', 'ja' => 'マネジメント', 'ko' => '매니지먼트', 'zh_CN' => '管理机构', 'ar' => 'الإدارات', 'ru' => 'организация'],
    'session' => ['id' => 'sesi', 'ja' => 'セッション', 'ko' => '세션', 'zh_CN' => '会话', 'ar' => 'الجلسة', 'ru' => 'сессия'],
    'asset' => ['id' => 'aset', 'ja' => 'アセット', 'ko' => '에셋', 'zh_CN' => '素材', 'ar' => 'أصل', 'ru' => 'актив'],
    'queue' => ['id' => 'antrean', 'ja' => 'キュー', 'ko' => '대기열', 'zh_CN' => '队列', 'ar' => 'قائمة الانتظار', 'ru' => 'очередь'],
    'storage' => ['id' => 'penyimpanan', 'ja' => 'ストレージ', 'ko' => '저장소', 'zh_CN' => '存储服务', 'ar' => 'التخزين', 'ru' => 'хранилище'],
    // `test` (the credential / AI-connection action) renders as `uji` in `id` and
    // `测试` in `zh_CN`; the button label itself is `list_actions.test`. No locale
    // keeps the English word, so it is watched rather than allowed.
    'test' => ['id' => 'uji', 'ja' => 'テスト', 'ko' => '테스트', 'zh_CN' => '测试', 'ar' => 'اختبار', 'ru' => 'проверка'],
];

// Per-locale extras. Each entry needs a reason: it is a reviewed decision that the
// Latin word is what a user of that locale should read on the surface.
$accepted = [
    'id' => [
        // Loanwords an Indonesian product user reads as the normal term. The glossary
        // settles the concepts with a native rendering; these have none and the English
        // word is what ships. Mirrors the SPA `localeDuplicateEnglishKeys` list in the
        // sibling repo, which is the other half of the same decision.
        'provider' => 'loanword — no settled native rendering; the SPA allowlist accepts it too',
        'error' => 'loanword — "galat" is not what an id product user reads on an error surface',
        'dashboard' => 'loanword — no settled native rendering',
        'filter' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'upload' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'status' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'label' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'avatar' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'platform' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'marketplace' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'token' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'email' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'bank' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'audio' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'video' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'folder' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'total' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'subtotal' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'upgrade' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'downgrade' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'addon' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'host' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'model' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'margin' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'navbar' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'sidebar' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
        'thumbnail' => 'loanword — accepted in `localeDuplicateEnglishKeys`',
    ],
    'ja' => [],
    'ko' => [],
    'zh_CN' => [],
    'ar' => [],
    'ru' => [],
];

return [
    'machine_patterns' => $machinePatterns,
    'machine' => array_values(array_unique($machine)),
    'glossary' => $glossary,
    'accepted' => $accepted,
];
