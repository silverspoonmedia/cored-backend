<?php

return [
    'view_profile' => 'プロフィールを見る',
    'view_detail' => '表示',
    'manage' => '管理',
    'test' => 'テスト',
    'revoke' => '失効',
    'make_default' => 'デフォルトに設定',
    'activate' => '有効化',
    'deactivate' => '無効化',
    'set_settlement' => '決済通貨に設定',
    'remove' => '削除',
    // curated at 2026-09-25T21:30:00+07:00 — vTual claims hub row actions
    'verify_claim' => '申請を検証',
    'accept_anyway' => '確認なしで承認',
    // curated at 2026-09-27T19:20:00+07:00 — vTual incident triage queue row actions
    'acknowledge_incident' => '確認済みにする',
    'resolve_incident' => '解決済みにする',
    'open_credential' => '認証情報を開く',
    'open_profile' => 'プロフィールを開く',
    'confirm' => [
        'archive_management' => 'このマネジメントをアーカイブしますか？',
        // curated at 2026-09-25T21:30:00+07:00 — vTual claims hub row actions
        'cancel_claim_title' => '申請をキャンセル',
        'cancel_claim' => 'この申請をキャンセルしますか？bio のトークンは無効になり、最初からやり直す必要があります。',
        'accept_anyway_claim_title' => '確認なしで承認',
        'accept_anyway_claim' => 'チャンネルの bio にトークンがないまま、この申請を承認しますか？',
        'cancel_event' => 'このイベントを中止しますか？',
        'delete_service' => 'このサービスを削除しますか？',
        'delete_inference' => 'このレコードを削除しますか？元に戻せません。',
        'delete_named_title' => '「:name」を削除',
        'delete_menu' => '「:name」を削除してもよろしいですか？',
        'delete_post' => 'この投稿はゴミ箱に移動されます。後で復元できます。',
        'delete_microstock_credential' => 'この認証情報を削除しますか？',
        'delete_credential_title' => '認証情報を削除',
        'delete_platform_credential' => 'この認証情報を完全に削除しますか？',
        'revoke_credential_title' => '認証情報を失効',
        // curated at 2026-09-21T17:25:29+07:00 — ja curation wave
        'revoke_credential' => 'この認証情報を失効させますか？編集して再テストするまで、ワーカーはこの認証情報を使用しません。',
        'delete_ai_connection_title' => '接続を削除',
        // curated at 2026-09-21T17:25:29+07:00 — ja curation wave
        'delete_ai_connection' => 'この AI 接続を完全に削除しますか？このスラッグに紐づいているコンシューマーは、別の接続を紐づけるまでエンドポイントを利用できなくなります。',
        'remove_blocklist_title' => 'エントリを削除',
        'remove_blocklist' => 'このハッシュをブロックリストから削除しますか？同じチェックサムのファイルは再び公開共有できるようになります。',
        // curated at 2026-09-27T19:20:00+07:00 — vTual incident triage queue row actions
        'resolve_incident_title' => 'インシデントを解決',
        'resolve_incident' => 'このインシデントを解決済みにしますか？同じ障害が再発すると自動的に未対応に戻ります。',
    ],
];
