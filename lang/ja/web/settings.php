<?php

return [
    'title' => '設定',
    // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
    'subtitle' => '管理 UI で管理する運用設定です。',
    'read_only' => 'これらの設定は閲覧のみ可能です。',
    'source' => '現在のソース: :source',
    'groups' => [
        'platform' => 'プラットフォーム',
        'archivd' => 'Archivd',
        'microstock' => 'Microstock',
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
        'platform.two_factor.issuer' => '2FA発行者名（認証アプリ）',
        // Platform — hCaptcha
        'platform.hcaptcha.enabled' => 'hCaptcha有効',
        // Platform — notifications
        'platform.notifications.delivery' => '通知配信モード',
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
        'platform.payment.channel_fee_bearer' => 'チャネル手数料負担者',
        'platform.payment.reporting_timezone' => '決済レポートタイムゾーン',
        'platform.payment.prefer_channel_checkout' => 'チャネルネイティブ決済を優先',
        'platform.payment.default_buyer_phone' => 'デフォルト購入者電話（iPaymuフォールバック）',
        // Platform — mail identity
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'platform.mail.from_address' => 'メールの From アドレス',
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'platform.mail.from_name' => 'メールの From 名',
        // Platform — service
        'platform.service.grace_days' => 'サービス猶予日数',
        'platform.service.settlement_currency' => '決済通貨',
        'platform.service.purchase_max_quantity' => 'サービス購入最大数量',
        // Platform — inference fallbacks
        'platform.inference.default_provider_slug' => 'デフォルト推論プロバイダースラッグ',
        'platform.inference.default_input_per_million_usd' => 'デフォルト入力単価（100万トークンあたりUSD）',
        'platform.inference.default_output_per_million_usd' => 'デフォルト出力単価（100万トークンあたりUSD）',
        'platform.inference.unknown_input_per_million_usd' => '未登録モデル入力単価（100万トークンあたりUSD）',
        'platform.inference.unknown_output_per_million_usd' => '未登録モデル出力単価（100万トークンあたりUSD）',
        // Platform — Frankfurter FX
        'platform.frankfurter.cache_ttl' => 'Frankfurter FX鮮度ウィンドウ（秒）',
        'platform.frankfurter.timeout' => 'Frankfurter FX HTTPタイムアウト（秒）',
        // Archivd
        'archivd.max_file_size_bytes' => '最大ファイルサイズ（バイト）',
        'archivd.max_files_per_batch' => 'バッチあたり最大ファイル数',
        'archivd.max_concurrent_uploads' => '最大同時アップロード数',
        'archivd.max_concurrent_parts' => '最大同時マルチパート数',
        'archivd.chunk_size_bytes' => 'アップロードチャンクサイズ（バイト）',
        'archivd.multipart_threshold_bytes' => 'マルチパートアップロード閾値（バイト）',
        'archivd.max_presigned_parts_per_response' => 'レスポンスあたり最大プリサインドパート数',
        'archivd.presign_ttl_minutes' => 'プリサインドURL TTL（分）',
        'archivd.session_ttl_hours' => 'アップロードセッションTTL（時間）',
        'archivd.stale_upload_grace_hours' => '停滞アップロード猶予期間（時間）',
        'archivd.orphan_inactivity_minutes' => '孤立パート非アクティブタイムアウト（分）',
        'archivd.default_quota_bytes' => 'デフォルトストレージクォータ（バイト）',
        'archivd.base_owned_workspace_slots' => '基本所有ワークスペーススロット数',
        'archivd.disabled_regions' => '無効化されたストレージリージョン',
        'archivd.signed_download_ttl_minutes' => '署名付きダウンロードURL TTL（分）',
        'archivd.signed_download_cache_margin_seconds' => '署名付きダウンロードキャッシュ余裕（秒）',
        'archivd.public_download_signature_ttl_minutes' => '公開ダウンロード署名TTL（分）',
        'archivd.allowed_mime_prefixes' => '許可MIMEタイププレフィックス',
        'archivd.download_vault.enabled' => 'ダウンロードVaultプロキシ有効',
        // Microstock
        'microstock.daily_free.enabled' => '毎日無料ダウンロード有効',
        'microstock.daily_free.limit_per_user' => 'ユーザーあたり毎日無料ダウンロード上限',
        'microstock.daily_free.timezone' => '毎日無料リセットタイムゾーン',
        'microstock.ai_billing.markup_multiplier' => 'AI課金マークアップ倍率',
        'microstock.ai_billing.estimated_billable_micro_usd_per_asset' => 'アセットあたり推定課金マイクロUSD',
        'microstock.ai_billing.estimate_thinking_attempt_factor' => '思考試行推定係数',
        'microstock.ai.image_max_px' => 'AI画像最大寸法（px）',
        'microstock.ai.image_jpeg_quality' => 'AI画像JPEG品質',
        'microstock.ai.max_request_image_bytes' => 'リクエスト画像最大サイズ（バイト）',
        'microstock.ai.http_retries' => 'AI HTTPリトライ回数',
        'microstock.ai.thinking_visual_attempts' => '思考ビジュアル試行回数',
        'microstock.ai.batch_chunk' => 'AIバッチチャンクサイズ',
        'microstock.ai.batch_min_files' => 'AIバッチ最小ファイル数',
        'microstock.ai.phase.normal_after' => 'AIフェーズ: 通常移行まで（秒）',
        'microstock.ai.phase.grace_after' => 'AIフェーズ: 猶予移行まで（秒）',
        'microstock.ai.stage.vision_grace_after' => 'AIステージ: ビジョン猶予まで（秒）',
        'microstock.ai.stage.thinking_grace_after' => 'AIステージ: 思考猶予まで（秒）',
        'microstock.ai.stage.thinking_backlog_after' => 'AIステージ: 思考バックログまで（秒）',
        'microstock.ai.vision_image.delivery' => 'ビジョン画像配信モード',
        'microstock.ai.vision_image.variant_kind' => 'ビジョン画像バリアント種類',
        'microstock.ai.vision_image.signed_url_ttl_minutes' => 'ビジョン署名付きURL TTL（分）',
        'microstock.ai.inference_provider_slug' => 'Microstock推論プロバイダースラッグ',
        'microstock.ai.vision.connection' => 'ビジョン AI 接続（空＝デフォルト）',
        'microstock.ai.thinking.connection' => '思考 AI 接続（空＝デフォルト）',
        'microstock.ai.vision.model' => 'ビジョンモデル',
        'microstock.ai.vision.free_model' => 'ビジョン無料枠モデル（空＝有料モデル）',
        'microstock.ai.vision.timeout' => 'ビジョンHTTPタイムアウト（秒）',
        'microstock.ai.thinking.model' => '思考モデル',
        'microstock.ai.thinking.free_model' => '思考無料枠モデル（空＝有料モデル）',
        'microstock.ai.thinking.timeout' => '思考HTTPタイムアウト（秒）',
        'microstock.ai.upstream.window_seconds' => 'アップストリームトークン予算ウィンドウ（秒）',
        'microstock.ai.upstream.token_budget' => 'ウィンドウあたりアップストリームトークン予算',
        'microstock.ai.upstream.max_concurrent_vision' => '最大同時ビジョンリクエスト数',
        'microstock.ai.upstream.max_concurrent_thinking' => '最大同時思考リクエスト数',
        'microstock.ai.upstream.collection_vision_concurrency' => 'コレクションビジョン並行数',
        'microstock.ai.upstream.collection_thinking_concurrency' => 'コレクション思考並行数',
        'microstock.ai.upstream.solo_vision_concurrency' => 'ソロビジョン並行数',
        'microstock.ai.upstream.solo_thinking_concurrency' => 'ソロ思考並行数',
        'microstock.ai.upstream.release_after_seconds' => 'アップストリームスロット解放まで（秒）',
        'microstock.csv_disk' => 'CSVエクスポートディスク',
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-27T19:30:00+07:00 — ja curation wave
        // curated at 2026-09-27T19:30:00+07:00 — ja curation wave
        // curated at 2026-09-27T19:30:00+07:00 — ja curation wave
        // curated at 2026-09-27T19:30:00+07:00 — ja curation wave
    ],
    'hints' => [
        'platform.mail.from_address' => '送信メールのグローバルFromアドレス（輸送資格情報は.envのまま）。',
        'platform.payment.default_buyer_phone' => '購入者プロフィールに電話がない場合のフォールバック（iPaymu要件）。',
        'platform.frankfurter.timeout' => 'Frankfurter FX取得のHTTPタイムアウト。',
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'microstock.ai.vision.free_model' => '任意で無料枠モデルを上書きします。空の場合は有料のビジョンモデルを継承します。',
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'microstock.ai.thinking.free_model' => '任意で無料枠モデルを上書きします。空の場合は有料の思考モデルを継承します。',
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'microstock.ai.vision.connection' => 'ビジョン工程が使用する AI 接続のスラッグ（設定 → AI 接続）。空の場合はデフォルト接続を使用します。',
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'microstock.ai.thinking.connection' => '思考工程が使用する AI 接続のスラッグ（設定 → AI 接続）。空の場合はデフォルト接続を使用します。',
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'microstock.ai.upstream.window_seconds' => 'アップストリームのトークン予算を判定する参照ウィンドウです（上限の適用は同時実行数の制限で行います）。',
        'platform.jwt.refresh_skew' => 'アクセストークンの有効期限前にリフレッシュする余裕時間（分）。',
        'platform.notifications.delivery' => 'リアルタイムは WebSocket を使い、設定済みの PUSHER_* / ブロードキャスト資格情報が必要です。未設定の場合はベルが HTTP ポーリングにフォールバックします。',
        'platform.payment.channel_fee_bearer' => '決済チャネル手数料の負担者: 加盟店または顧客。',
        'platform.inference.default_provider_slug' => 'リクエストで指定がない場合のフォールバックプロバイダー。',
        'platform.inference.default_input_per_million_usd' => 'カタログ価格のない登録モデル向け暫定課金単価。',
        'platform.inference.unknown_input_per_million_usd' => '未登録モデル向け暫定課金単価。',
        'microstock.ai.phase.normal_after' => 'アセットが高速処理から通常処理に移行するまでの秒数。',
        'microstock.ai.phase.grace_after' => '猶予期間の処理が開始されるまでの秒数。',
        'microstock.ai.stage.vision_grace_after' => 'ビジョンステージが猶予モードに入るまでの秒数。',
        'microstock.ai.stage.thinking_grace_after' => '思考ステージが猶予モードに入るまでの秒数。',
        'microstock.ai.stage.thinking_backlog_after' => '思考ジョブがバックログキューに移るまでの秒数。',
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        // curated at 2026-09-27T19:30:00+07:00 — ja curation wave
        // curated at 2026-09-21T17:29:57+07:00 — ja curation wave
        'identity.discord.connections_snapshot_max_age_minutes' => '検証に失敗したときに Discord API から最新情報を取得するまで、保存済みの Discord 接続スナップショットを信頼する時間です。',
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
