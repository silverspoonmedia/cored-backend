<?php

return [
    'title' => '設定',
    'subtitle' => '管理UIで管理する運用設定。',
    'read_only' => 'これらの設定は閲覧のみ可能です。',
    'source' => '現在のソース: :source',
    'groups' => [
        'platform' => 'プラットフォーム',
        'file_hosting' => 'File Hosting',
        'microstock' => 'Microstock',
        'vtual' => 'vTual',
        'notification_audiences' => '通知オーディエンス',
        'currencies' => '通貨',
    ],
    'fields' => [
        'platform.jwt.ttl' => 'JWTアクセストTL（分）',
        'platform.hcaptcha.enabled' => 'hCaptcha有効',
    ],
    'hints' => [],
];
