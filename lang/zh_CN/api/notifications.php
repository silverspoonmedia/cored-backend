<?php

return [
    'list_success' => '通知已成功获取。',
    'unread_count_success' => '未读通知数量已成功获取。',
    'marked_read' => '通知已标记为已读。',
    'marked_all_read' => '所有通知已标记为已读。',
    'not_visible' => '你无权访问此通知。',
    // Message templates per notification type. Replacements come from the
    // notification `data` payload (:name, :email, :actor, :role, …).
    'types' => [
        'user_created' => '新用户 :name（:email）已由 :actor 添加。',
        'user_updated' => ':actor 更新了用户 :name（:email）。',
        'user_deleted' => ':actor 删除了用户 :name（:email）。',
        'account_updated' => '你的账户已由 :actor 更新。',
        'service_subscription_grace' => '你的 :service 订阅已结束。请在 :grace_days 天内（截止 :grace_ends_at）续费，否则超出部分的文件将被删除，以恢复到免费层级限制。',
        'service_subscription_expired' => '你的 :service 订阅宽限期已结束。现已执行免费层级存储限制（已删除 :deleted 个文件）。',
        'inference_unregistered_model' => '未注册模型 :resolved_model 在 :provider_slug 上按临时费率计费（每 1M 输入 $:input_per_million_usd / 输出 $:output_per_million_usd）。请在模型目录中分类，以完成计费。',
    ],
    'audiences' => [
        'list_success' => '通知受众已加载。',
        'show_success' => '通知受众已加载。',
        'updated' => '通知受众已更新。',
        'unknown_type' => '未知通知类型。',
    ],
];
