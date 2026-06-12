<?php

return [
    'tree_success' => 'ナビゲーションツリーを取得しました。',
    'list_success' => 'メニューを取得しました。',
    'show_success' => 'メニューを取得しました。',
    'trashed_success' => '削除済みメニューを取得しました。',
    'created' => 'メニューを作成しました。',
    'updated' => 'メニューを更新しました。',
    'deleted' => 'メニューを削除しました。',
    'restored' => 'メニューを復元しました。',
    'not_found' => 'メニューが見つかりません。',
    'has_children' => 'このメニューには子項目があります。子孫ごと削除するには cascade=true を指定してください。',
    'invalid_route' => 'ルートは登録済みのルート名である必要があります。',
    'position' => [
        'reference_required' => 'before/after の配置には reference_id が必要です。',
        'reference_not_in_group' => 'reference_id が同じ兄弟グループ内にありません。',
        'invalid_position' => 'position は before または after である必要があります。',
        'reference_same_menu' => 'reference_id は同じメニューにできません。',
        'reference_wrong_group' => '基準メニューは対象グループと parent_id および type を共有する必要があります。',
    ],
    'parent_restore_failed' => '復元できません: 親メニューが存在しないか、まだ削除されています。先に親を復元してください。',
];
