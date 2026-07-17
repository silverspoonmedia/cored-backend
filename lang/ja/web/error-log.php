<?php

return [
    'title' => 'エラーログ',
    'breadcrumb' => 'エラーログ',
    'list_title' => 'アプリケーションエラー',
    'show_title' => 'エラー詳細',
    'show_breadcrumb' => '詳細',
    'show_card_title' => 'エラーグループ',
    'empty' => 'まだエラーはありません。',
    'view_detail' => '表示',
    'back_to_list' => '一覧に戻る',
    'status_updated' => 'ステータスを更新しました。',
    'fields' => [
        'status' => 'ステータス',
        'level' => 'レベル',
        'message' => 'メッセージ',
        'exception_class' => '例外',
        'location' => '場所',
        'occurrence_count' => '発生回数',
        'first_seen_at' => '初回',
        'last_seen_at' => '最終',
        'fingerprint' => 'フィンガープリント',
        'resolved_by' => '対応者',
    ],
    'sections' => [
        'context' => 'コンテキスト',
        'trace' => 'スタックトレース',
    ],
    'columns' => [
        'level' => 'レベル',
        'message' => 'メッセージ',
        'count' => '回数',
        'status' => 'ステータス',
        'last_seen' => '最終',
        'actions' => '操作',
    ],
    'filters' => [
        'all_statuses' => 'すべてのステータス',
        'all_levels' => 'すべてのレベル',
        'all_types' => 'すべての種類',
        'search' => 'メッセージまたはクラスを検索…',
        'apply' => '絞り込み',
    ],
    'statuses' => [
        'open' => '未対応',
        'resolved' => '解決済み',
        'ignored' => '無視',
    ],
    'types' => [
        'exception' => '例外',
        'log' => 'ログ',
    ],
    'actions' => [
        'resolve' => '解決済みにする',
        'ignore' => '無視する',
        'reopen' => '再開する',
    ],
];
