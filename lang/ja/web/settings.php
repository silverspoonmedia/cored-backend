<?php

return [
    'title' => '設定',
    // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
    'subtitle' => '管理 UI で管理する運用設定です。',
    'read_only' => 'これらの設定は閲覧のみ可能です。',
    'source' => '現在のソース: :source',
    'groups' => [
        'platform' => 'プラットフォーム',
        'notification_audiences' => '通知オーディエンス',
        'currencies' => '通貨',
    ],
    'fields' => [
        // Platform — JWT
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'platform.jwt.ttl' => 'JWT アクセストークン TTL（分）',
        'platform.jwt.refresh_ttl' => 'JWTリフレッシュ期間（分）',
        'platform.jwt.remember_ttl' => 'JWTリメンバーミーTTL（分）',
        'platform.jwt.refresh_skew' => 'JWTサイレントリフレッシュ余裕（分）',
        'platform.jwt.blacklist_grace_period' => 'JWTブラックリスト猶予（秒）',
        // Platform — auth / 2FA
        'platform.auth.default_role' => '新規ユーザーのデフォルトロール',
        'platform.auth.verification_expire' => 'メール確認リンク有効期限（分）',
        'platform.two_factor.window' => '2FA TOTP検証ウィンドウ',
        'platform.two_factor.issuer' => '2FA発行�
名（認証アプリ）',
        // Platform — hCaptcha
        'platform.hcaptcha.enabled' => 'hCaptcha有効',
        // Platform — notifications
        'platform.notifications.delivery' => '通知�
�信モード',
        'platform.notifications.polling_interval' => '通知ポーリング間隔（秒）',
        // Platform — error log
        'platform.errorlog.enabled' => '永続エラーログ有効',
        'platform.errorlog.clean_after_days' => 'エラーログ保持期間（日）',
        'platform.errorlog.max_message_length' => 'エラーログ最大メッセージ長',
        'platform.errorlog.max_trace_length' => 'エラーログ最大トレース長',
        'platform.errorlog.max_context_json_length' => 'エラーログ最大コンテキストJSON長',
        // Platform — activity log
        'platform.activitylog.enabled' => 'アクティビティログ有効',
        'platform.activitylog.buffer_enabled' => 'アクティビティログバッファ有効',
        // Platform — localization
        'platform.localization.locale_cookie' => 'ロケールCookie名',
        'identity.discord.connections_snapshot_max_age_minutes' => 'Discord接続スナップショット最大有効時間（分）',
        'platform.localization.locale_cookie_minutes' => 'ロケールCookie有効期間（分）',
        // Platform — payment
        'platform.payment.channel_fee_bearer' => 'チャネル手数料負�
�
',
        'platform.payment.reporting_timezone' => '決済レポートタイムゾーン',
        'platform.payment.prefer_channel_checkout' => 'チャネルネイティブ決済を優�
�',
        'platform.payment.default_buyer_phone' => 'デフォルト購�
��
電話（iPaymuフォールバック）',
        // Platform — mail identity
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'platform.mail.from_address' => 'メールの From アドレス',
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'platform.mail.from_name' => 'メールの From 名',
        // Platform — service
        'platform.service.grace_days' => 'サービス猶予日数',
        'platform.service.settlement_currency' => '決済通貨',
        'platform.service.purchase_max_quantity' => 'サービス購�
�最大数量',
        // Platform — inference fallbacks
        'platform.inference.default_provider_slug' => 'デフォルト推論プロバイダースラッグ',
        'platform.inference.default_input_per_million_usd' => 'デフォルト�
�力単価（100万トークンあたりUSD）',
        'platform.inference.default_output_per_million_usd' => 'デフォルト出力単価（100万トークンあたりUSD）',
        'platform.inference.unknown_input_per_million_usd' => '未登録モデル�
�力単価（100万トークンあたりUSD）',
        'platform.inference.unknown_output_per_million_usd' => '未登録モデル出力単価（100万トークンあたりUSD）',
        // Platform — Frankfurter FX
        'platform.frankfurter.cache_ttl' => 'Frankfurter FX鮮度ウィンドウ（秒）',
        'platform.frankfurter.timeout' => 'Frankfurter FX HTTPタイムアウト（秒）',
�開ダウンロード署名TTL（分）',
�信モード',
    ],
    'hints' => [
        'platform.mail.from_address' => '送信メールのグローバルFromアドレス（輸送資格�
報は.envのまま）。',
        'platform.payment.default_buyer_phone' => '購�
��
プロフィールに電話がない場合のフォールバック（iPaymu要件）。',
        'platform.frankfurter.timeout' => 'Frankfurter FX取得のHTTPタイムアウト。',
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
�ウィンドウです（上限の適用は同時実行数の制限で行います）。',
        'platform.jwt.refresh_skew' => 'アクセストークンの有効期限前にリフレッシュする余裕時間（分）。',
        'platform.notifications.delivery' => 'リアルタイムは WebSocket を使い、設定済みの PUSHER_* / ブロードキャスト資格�
報が�
要です。未設定の場合はベルが HTTP ポーリングにフォールバックします。',
        'platform.payment.channel_fee_bearer' => '決済チャネル手数料の負�
�
: 加盟店または顧客。',
        'platform.inference.default_provider_slug' => 'リクエストで指定がない場合のフォールバックプロバイダー。',
        'platform.inference.default_input_per_million_usd' => 'カタログ価格のない登録モデル向け暫定課金単価。',
        'platform.inference.unknown_input_per_million_usd' => '未登録モデル向け暫定課金単価。',
�るまでの秒数。',
�るまでの秒数。',
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'identity.discord.connections_snapshot_max_age_minutes' => '検証に失敗したときに Discord API から最新�
報を取得するまで、保存済みの Discord 接続スナップショットを信頼する時間です。',
    ],
    'options' => [
        'platform' => [
            'notifications' => [
                'delivery' => [
                    'polling' => 'HTTP ポーリング',
                    'realtime' => 'WebSocket（リアルタイム）',
                ],
            ],
        ],
    ],
];
