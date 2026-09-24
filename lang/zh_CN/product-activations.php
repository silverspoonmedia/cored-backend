<?php

declare(strict_types=1);

return [
    'list_success' => '产品激活记录已获取。',
    'show_success' => '产品激活记录已获取。',
    'admin_list_success' => '产品激活记录已获取。',
    'stats_success' => '产品激活统计数据已获取。',
    'suspended' => '产品激活已暂停。',
    'reactivated' => '产品激活已重新启用。',
    'approved' => '产品激活已批准。',
    'rejected' => '产品激活已拒绝。',

    'not_found' => '未找到 :product 的产品激活记录。',
    'unknown_product' => '未知产品键：:product。',
    'already_active' => '产品已处于启用状态。',
    'cannot_approve' => '无法批准状态为 :state 的激活记录。',
    'cannot_reject' => '无法拒绝状态为 :state 的激活记录。',
    'cannot_suspend' => '无法暂停状态为 :state 的激活记录。',
    'cannot_reactivate' => '无法重新启用状态为 :state 的激活记录。',
    'type_mismatch' => '产品 :product 需要激活类型 :expected，但收到 :actual。',
    'inactive' => '产品 :product 处于 :state 状态，访问被拒绝。',
    // curated at 2026-09-22T12:51:54+07:00 — zh_CN curation wave
    'not_provisioned' => '产品 :product 尚未开通。',
    'auth_required' => '需要身份验证。',

    // UI labels (Blade)
    'title' => '产品激活',
    // curated at 2026-09-22T12:51:54+07:00 — zh_CN curation wave
    'subtitle' => '按用户记录的产品激活注册表。',
    'col_user' => '用户',
    'col_product' => '产品',
    'col_type' => '类型',
    'col_state' => '状态',
    'col_source' => '来源',
    'col_activated_at' => '激活时间',
    'col_suspended_at' => '暂停时间',
    'col_expired_at' => '过期时间',
    'col_updated_at' => '更新时间',
    'col_actions' => '操作',
    'filter_product' => '产品',
    'filter_state' => '状态',
    'filter_all' => '全部',
    'action_suspend' => '暂停',
    'action_reactivate' => '重新启用',
    'action_approve' => '批准',
    'action_reject' => '拒绝',
    'reason_label' => '原因',
    'reason_placeholder' => '此操作的原因（可选）',
    'state_pending' => '待处理',
    'state_active' => '启用',
    'state_suspended' => '已暂停',
    'state_expired' => '已过期',
    'type_implicit' => '隐式',
    'type_explicit' => '显式',

    // v2 — per-product insights UI
    'products_title' => '产品域',
    'products_subtitle' => '按产品域查看洞察和控制项。',
    'products_empty' => '尚未注册产品域。',
    'products_list_success' => '产品摘要已获取。',
    'product_show_success' => '产品详情已获取。',
    'user_detail_success' => '用户产品详情已获取。',
    'product_title' => '产品：:product',
    'view_product' => '查看洞察',
    'back_to_products' => '返回产品列表',
    'fulfillment_target' => '履约目标',
    'no_activations' => '此产品暂无激活记录。',
    'pagination' => '激活记录分页',

    // Metric labels (keys mirror ProductInsightService output)
    'metric_total_storage_used_bytes' => '存储使用总量',
    'metric_total_files' => '文件总数',
    'metric_total_workspaces' => '工作区总数',
    'metric_paid_users_count' => '付费层用户',
    'metric_free_users_count' => '免费层用户',
    'metric_grace_users_count' => '宽限期用户',
    'metric_storage_used_bytes' => '存储使用量',
    'metric_file_count' => '文件数',
    'metric_workspace_count_owned' => '拥有的工作区数',

    // Tier labels
    'tier' => '层级',
    'tier_free' => '免费',
    'tier_paid' => '付费',
    'tier_grace' => '宽限期',
    'tier_n/a' => '不适用',
];
