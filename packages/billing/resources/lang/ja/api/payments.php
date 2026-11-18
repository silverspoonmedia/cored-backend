<?php

return [
    'success' => '支払いが完了しました。',
    'failed' => '支払いに失敗しました。もう一度お試しください。',
    'pending' => '支払いを処理中です。',

    'gateways' => [
        'list' => '決済ゲートウェイ一覧を読み込みました。',
        'show' => '決済ゲートウェイの詳細を読み込みました。',
        'created' => '決済ゲートウェイを作成しました。',
        'updated' => '決済ゲートウェイを更新しました。',
        'deleted' => '決済ゲートウェイを削除しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'inactive' => '選択した決済ゲートウェイは有効になっていません。',
        'none_active' => '有効な決済ゲートウェイが設定されていません。',
    ],

    'invoices' => [
        'list' => '請求書一覧を読み込みました。',
        'show' => '請求書の詳細を読み込みました。',
        'checkout_channels' => 'チェックアウトの決済チャネルを読み込みました。',
        'checkout_channels_not_payable' => 'この請求書は現在支払えません。',
    ],

    'income' => [
        'overview' => '決済収入の概要を読み込みました。',
        'invoices' => '決済収入の請求書一覧を読み込みました。',
    ],

    'subscriptions' => [
        'list' => 'サブスクリプション一覧を読み込みました。',
        'show' => 'サブスクリプションの詳細を読み込みました。',
        'created' => 'サブスクリプションを作成しました。',
        'updated' => 'サブスクリプションを更新しました。',
        'deleted' => 'サブスクリプションを削除しました。',
    ],

    'checkout' => [
        'active_session_pending' => '前回の支払いセッションがプロバイダー側で保留中です。チャネルを切り替える前に期限切れまたは完了を待ってください。',
        'gateway_provider_mismatch' => '請求書の決済ゲートウェイが要求されたチェックアウトプロバイダーと一致しません。',
    ],

    'channels' => [
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'list' => '決済チャネルを読み込みました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'show' => '決済チャネルを読み込みました。',
        'updated' => '決済チャネルを更新しました。',
        'checkout_created' => '支払い手順を作成しました。',
        'checkout_failed' => 'このチャネルのすべての決済ルートが失敗しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'route_updated' => '決済チャネルのルートを更新しました。',
        'not_found' => '決済チャネルが見つかりません。',
        'not_available' => '選択した決済チャネルは利用できません。',
        'unsupported_gateway' => 'このチャネル用の決済ゲートウェイが設定されていません。',
        'policy_managed_route' => 'このルートはルーティングポリシーで管理されています。有効/無効のみ変更できます。',
    ],

    'midtrans' => [
        'snap_created' => 'Snapトランザクションを作成しました。',
        'charge_created' => 'Core APIチャージを作成しました。',
        'status_retrieved' => 'トランザクションステータスを取得しました。',
        'cancel_ok' => 'トランザクションをキャンセルしました。',
        'expire_ok' => 'トランザクションを期限切れにしました。',
        'refund_ok' => '返金を処理しました。',
        'approve_ok' => 'トランザクションを承認しました。',
        'deny_ok' => 'トランザクションを拒否しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'sync_ok' => '請求書のステータスを Midtrans と同期しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'invoice_not_midtrans' => '請求書は Midtrans ゲートウェイに紐づいていません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'invoice_not_payable' => '支払いを開始するには、請求書のステータスが pending または challenge である必要があります。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'external_sync_failed' => 'チェックアウトを開始する前に Midtrans の支払いステータスを確認できませんでした。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'webhook_invalid_json' => 'JSON ボディが無効です。',
        'webhook_invalid_signature' => '無効な通知署名です。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'webhook_missing_order' => '通知に order_id がありません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'webhook_ack_no_status' => '通知を受け付けました（ステータスを特定できませんでした）。',
        'webhook_orphan' => '通知を受け付けました（一致する請求書がありません）。',
        'webhook_ok' => '通知を処理しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'webhook_ping' => 'Midtrans の webhook URL に到達可能です（通知には POST を使用してください）。',
        'webhook_subscription_ack' => 'サブスクリプション通知を受け付けました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'open_api_public_key_missing' => 'Midtrans の Open API 公開鍵が設定されていません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'open_api_signature_invalid' => 'Open API 通知の X-SIGNATURE が無効か、検証できません。',
    ],

    'ipaymu' => [
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'snap_created' => 'iPaymu のホスト型チェックアウトセッションを作成しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'charge_created' => 'iPaymu の直接支払いを作成しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'status_retrieved' => 'iPaymu からトランザクションのステータスを取得しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'sync_ok' => '請求書のステータスを iPaymu と同期しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'invoice_not_ipaymu' => '請求書は iPaymu ゲートウェイに紐づいていません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'invoice_not_payable' => '支払いを開始するには、請求書のステータスが pending または challenge である必要があります。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'external_sync_failed' => 'チェックアウトを開始する前に iPaymu の支払いステータスを確認できませんでした。',
        'active_payment_exists' => 'この請求書の支払いセッションが進行中です。新しいチェックアウトを開始する前に完了またはキャンセルしてください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'invoice_not_found' => 'この order id の iPaymu 請求書が見つかりません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'missing_transaction_id' => '請求書にはまだ iPaymu のトランザクション ID がありません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'buyer_required' => 'リクエスト、請求対象、またはログイン中のユーザープロフィールから購入者情報を取得できませんでした。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'webhook_invalid_json' => 'JSON ボディが無効です。',
        'webhook_invalid_signature' => '無効な通知署名です。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'webhook_invalid_va' => 'VA ヘッダーが設定済みの iPaymu VA と一致しません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'webhook_ack_no_status' => '通知を受け付けました（ステータスを特定できませんでした）。',
        'webhook_orphan' => '通知を受け付けました（一致する請求書がありません）。',
        'webhook_ok' => '通知を処理しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'webhook_ping' => 'iPaymu の webhook URL に到達可能です（通知には POST を使用してください）。',
    ],
];
