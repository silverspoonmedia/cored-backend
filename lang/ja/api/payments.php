<?php

return [
    'success' => '支払いが完了しました。',
    'failed' => '支払いに失敗しました。もう一度お試しください。',
    'pending' => '支払いを処理中です。',

    'gateways' => [
        'list' => '決済ゲートウェイを取得しました。',
        'show' => '決済ゲートウェイを取得しました。',
        'created' => '決済ゲートウェイを作成しました。',
        'updated' => '決済ゲートウェイを更新しました。',
        'deleted' => '決済ゲートウェイを削除しました。',
        'inactive' => '選択した決済ゲートウェイは有効ではありません。',
        'none_active' => '有効な決済ゲートウェイが設定されていません。',
    ],

    'invoices' => [
        'list' => '請求書を取得しました。',
        'show' => '請求書を取得しました。',
    ],

    'subscriptions' => [
        'list' => 'サブスクリプションを取得しました。',
        'show' => 'サブスクリプションを取得しました。',
        'created' => 'サブスクリプションを作成しました。',
        'updated' => 'サブスクリプションを更新しました。',
        'deleted' => 'サブスクリプションを削除しました。',
    ],

    'checkout' => [
        'active_session_pending' => '前回の支払いセッションがプロバイダー側で保留中です。チャネルを切り替える前に期限切れまたは完了を待ってください。',
        'gateway_provider_mismatch' => '請求書の決済ゲートウェイが要求されたチェックアウトプロバイダーと一致しません。',
    ],

    'channels' => [
        'list' => '決済チャネルを取得しました。',
        'show' => '決済チャネルを取得しました。',
        'updated' => '決済チャネルを更新しました。',
        'checkout_created' => '支払い手順を作成しました。',
        'checkout_failed' => 'このチャネルのすべての決済ルートが失敗しました。',
        'route_updated' => '決済チャネルルートを更新しました。',
        'not_found' => '決済チャネルが見つかりません。',
        'not_available' => '選択した決済チャネルは利用できません。',
        'unsupported_gateway' => 'このチャネル用の決済ゲートウェイが設定されていません。',
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
        'sync_ok' => '請求書ステータスをMidtransと同期しました。',
        'invoice_not_midtrans' => '請求書はMidtransゲートウェイにリンクされていません。',
        'invoice_not_payable' => '支払いを開始するには請求書がpendingまたはchallengeである必要があります。',
        'external_sync_failed' => 'チェックアウト開始前にMidtransの支払いステータスを確認できませんでした。',
        'webhook_invalid_json' => '無効なJSONボディです。',
        'webhook_invalid_signature' => '無効な通知署名です。',
        'webhook_missing_order' => '通知にorder_idがありません。',
        'webhook_ack_no_status' => '通知を受け付けました（ステータスを解決できませんでした）。',
        'webhook_orphan' => '通知を受け付けました（一致する請求書がありません）。',
        'webhook_ok' => '通知を処理しました。',
        'webhook_ping' => 'Midtrans webhook URLに到達可能です（通知にはPOSTを使用してください）。',
        'webhook_subscription_ack' => 'サブスクリプション通知を受け付けました。',
        'open_api_public_key_missing' => 'Midtrans Open API公開鍵が設定されていません。',
        'open_api_signature_invalid' => 'Open API通知のX-SIGNATUREが無効または検証できません。',
    ],

    'ipaymu' => [
        'snap_created' => 'iPaymuホスト型チェックアウトセッションを作成しました。',
        'charge_created' => 'iPaymu直接支払いを作成しました。',
        'status_retrieved' => 'iPaymuからトランザクションステータスを取得しました。',
        'sync_ok' => '請求書ステータスをiPaymuと同期しました。',
        'invoice_not_ipaymu' => '請求書はiPaymuゲートウェイにリンクされていません。',
        'invoice_not_payable' => '支払いを開始するには請求書がpendingまたはchallengeである必要があります。',
        'external_sync_failed' => 'チェックアウト開始前にiPaymuの支払いステータスを確認できませんでした。',
        'active_payment_exists' => 'この請求書の支払いセッションが進行中です。新しいチェックアウトを開始する前に完了またはキャンセルしてください。',
        'invoice_not_found' => 'このorder idのiPaymu請求書が見つかりません。',
        'missing_transaction_id' => '請求書にまだiPaymuトランザクションIDがありません。',
        'buyer_required' => '購入者情報を提供するか、顧客フィールド付きの注文/サブスクリプションに請求書を添付してください。',
        'webhook_invalid_json' => '無効なJSONボディです。',
        'webhook_invalid_signature' => '無効な通知署名です。',
        'webhook_invalid_va' => 'VAヘッダーが設定されたiPaymu VAと一致しません。',
        'webhook_ack_no_status' => '通知を受け付けました（ステータスを解決できませんでした）。',
        'webhook_orphan' => '通知を受け付けました（一致する請求書がありません）。',
        'webhook_ok' => '通知を処理しました。',
        'webhook_ping' => 'iPaymu webhook URLに到達可能です（通知にはPOSTを使用してください）。',
    ],
];
