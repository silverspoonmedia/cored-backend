<?php

return [
    'creator' => [
        'list_success' => 'クリエイター一覧を読み込みました。',
        'show_success' => 'クリエイターを読み込みました。',
        'created' => 'クリエイターを作成しました。',
        'updated' => 'クリエイターを更新しました。',
        'archived' => 'クリエイターをアーカイブしました。',
        'name_required' => '表示名は必須です。',
        'slug_taken' => 'このクリエイタースラッグは既に使用されています。',
    ],
    'management' => [
        'list_success' => 'マネジメント一覧を読み込みました。',
        'show_success' => 'マネジメントを読み込みました。',
        'created' => 'マネジメントを作成しました。',
        'updated' => 'マネジメントを更新しました。',
        'name_required' => 'マネジメント名は必須です。',
        'slug_taken' => 'このマネジメントスラッグは既に使用されています。',
        'creator_assigned' => 'クリエイターをマネジメントに割り当てました。',
        'creator_unassigned' => 'クリエイターの割り当てを解除しました。',
        'assignment_not_found' => '有効なマネジメント割り当てが見つかりません。',
    ],
    'channel' => [
        'list_success' => 'チャンネル一覧を読み込みました。',
        'show_success' => 'チャンネルを読み込みました。',
        'created' => 'チャンネルを登録しました。',
        'platform_unknown' => '不明なプラットフォームです。',
        'platform_disabled' => 'このプラットフォームはまだ有効ではありません。',
        'external_id_required' => '外部チャンネル ID は必須です。',
        'external_id_taken' => 'このプラットフォーム ID のチャンネルは既に存在します。',
    ],
    'platform' => [
        'list_success' => 'プラットフォーム一覧を読み込みました。',
    ],
    'policy' => [
        'denied' => 'この操作を行う権限がありません。',
        'access_denied' => 'この vTual リソースにアクセスできません。',
        'mutate_denied' => 'この vTual リソースを変更できません。',
        'members_denied' => 'このリソースのメンバーを管理できません。',
        'assign_denied' => 'このマネジメントにクリエイターを割り当てできません。',
    ],
];
