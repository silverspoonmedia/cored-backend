<?php

return [
    'list_success' => '通知を取得しました。',
    'unread_count_success' => '未読通知数を取得しました。',
    'marked_read' => '通知を既読にしました。',
    'marked_all_read' => 'すべての通知を既読にしました。',
    'not_visible' => 'この通知にアクセスする権限がありません。',
    // Message templates per notification type. Replacements come from the
    // notification `data` payload (:name, :email, :actor, :role, …).
    'types' => [
        'user_created' => '新しいユーザー :name (:email) が :actor によって追加されました。',
        'account_updated' => 'あなたのアカウントが :actor によって更新されました。',
        'service_subscription_grace' => ':service のサブスクリプションが終了しました。:grace_days 日以内（:grace_ends_at まで）に更新しないと、無料枠を超えるファイルが削除されます。',
        'service_subscription_expired' => ':service の猶予期間が終了しました。無料枠が適用され、:deleted 件のファイルが削除されました。',
    ],
];
