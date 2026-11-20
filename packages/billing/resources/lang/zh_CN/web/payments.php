<?php

return [
    'gateways' => [
        'title' => '支付网关',
        'list_title' => '支付网关列表',
        'create' => '添加网关',
        'create_title' => '创建支付网关',
        'edit_title' => '编辑支付网关',
        'form' => [
            'name' => '系统名称',
            'display_name' => '显示名称',
            'priority' => '优先级',
            'is_active' => '启用',
        ],
        'confirm_delete' => '删除此支付网关？',
        'datatable' => [
            'empty' => '尚未配置支付网关。',
            'zero_records' => '没有匹配搜索条件的支付网关。',
            'columns' => [
                'name' => '名称',
                'display_name' => '显示名称',
                'is_active' => '启用',
                'priority' => '优先级',
            ],
        ],
    ],

    'show' => [
        'payment_not_started' => '尚未选择付款方式',
        'payment_pending' => '等待付款',
        'status' => [
            'paid' => '已付款',
            'expired' => '已过期',
            'cancelled' => '已取消',
            'failed' => '失败',
        ],
        'payment_steps' => [
            'aria' => '付款步骤',
            'choose_channel' => '选择方式',
            'choose_channel_hint' => '选择 QRIS、银行转账或电子钱包。',
            'pay' => '付款',
            'pay_hint' => '按照显示的说明完成付款。',
            'confirm' => '确认',
            'confirm_hint' => '支付提供商确认付款后，状态会更新。',
        ],
    ],

    'invoices' => [
        'title' => '发票',
        'list_title' => '发票列表',
        'show_title' => '发票详情',
        'show_card_title' => '发票 :number',
        'view_order' => '查看订单',
        'view_purchase' => '查看服务购买记录',
        'back_to_list' => '返回发票列表',
        'datatable' => [
            'empty' => '未找到发票。',
            'zero_records' => '没有匹配搜索条件的发票。',
            'columns' => [
                'invoice_number' => '发票编号',
                'status' => '状态',
                'subtotal' => '小计',
                // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
                'fee' => '支付手续费',
                'total' => '客户应付总额',
                'created_at' => '创建时间',
                'actions' => '操作',
            ],
        ],
        'export_csv' => '导出 CSV',
        'fields' => [
            'invoice_number' => '发票编号',
            'status' => '状态',
            'payment_method' => '付款方式',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'gateway' => '支付网关',
            'subtotal' => '服务价格',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'fee' => '支付手续费（买家）',
            'total' => '客户应付总额',
            'currency' => '货币',
            'paid_at' => '付款时间',
            'expired_at' => '过期时间',
            'created_at' => '创建时间',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'payment_route' => '结账路由',
        ],
    ],

    'income' => [
        'title' => '付款收入',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'subtitle' => '来自已付款发票的收入与现金收款。服务价格是您的产品收入；手续费是向买家收取并转交给提供商的附加费用。',
        'loading' => '加载中…',
        'load_failed' => '无法加载收入数据。',
        'empty_invoices' => '此期间没有已付款发票。',
        'empty_breakdown' => '此期间没有数据。',
        'invoices_title' => '已付款发票',
        'pagination' => '第 :page 页，共 :total 页',
        'filters' => [
            'from' => '开始日期',
            'to' => '结束日期',
            'basis' => '日期依据',
            'basis_paid_at' => '付款日期（paid_at）',
            'basis_withdrawable_at' => '结算日期（withdrawable_at）',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'gateway' => '支付网关',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'all_gateways' => '所有支付网关',
            'apply' => '应用',
        ],
        'summary' => [
            'service_revenue' => '服务收入',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'service_revenue_hint' => '发票小计总和（您的产品销售金额）。',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'buyer_fees' => '买家支付手续费',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'buyer_fees_hint' => '向客户收取的渠道附加费。',
            'cash_collected' => '已收现金',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'cash_collected_hint' => '客户支付的总额（小计 + 手续费）。',
            'invoice_count' => '已付款发票',
            'avg_order_value' => '平均订单金额',
        ],
        'pipeline' => [
            'title' => '资金流程',
            'invoices' => '发票',
            'awaiting_settlement' => '等待支付网关结算',
            'ready_to_disburse' => '可发放',
            'in_payout' => '发放中',
            'disbursed' => '已发放至银行',
            'failed_payout' => '发放失败',
        ],
        'breakdown' => [
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'gateway' => '按支付网关',
            'channel' => '按渠道',
            'product' => '按产品类型',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'label' => '名称',
            'invoices' => '发票',
            'service_revenue' => '服务收入',
            'cash_collected' => '已收现金',
        ],
        'daily' => [
            'title' => '每日趋势',
            'date' => '日期',
            'invoices' => '发票',
            'service_revenue' => '服务收入',
            'cash_collected' => '已收现金',
        ],
    ],

    'subscriptions' => [
        'title' => '订阅',
        'list_title' => '订阅列表',
        'create_title' => '添加订阅',
        'edit_title' => '编辑订阅',
        'show_title' => '订阅详情',
        'show_card_title' => '订阅 :number',
        'add_button' => '添加订阅',
        'new_card_title' => '新建订阅',
        'edit_card_title' => '编辑订阅：:number',
        'back_to_list' => '返回订阅列表',
        'datatable' => [
            'empty' => '未找到订阅。',
            'zero_records' => '没有匹配搜索条件的订阅。',
            'columns' => [
                'subscription_number' => '订阅编号',
                'subscriber_name' => '订阅者',
                // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
                'plan_name' => '方案',
                'status' => '状态',
                'amount' => '金额',
                'actions' => '操作',
            ],
        ],
        'fields' => [
            'subscriber_name' => '订阅者姓名',
            'subscriber_email' => '订阅者邮箱',
            'subscriber_phone' => '订阅者电话',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'plan_name' => '方案名称',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'plan_description' => '方案描述',
            'billing_cycle' => '计费周期',
            'amount' => '金额',
            'currency' => '货币',
            'status' => '状态',
            'auto_renew' => '自动续费',
            'started_at' => '开始时间',
            'expires_at' => '过期时间',
            'trial_ends_at' => '试用结束时间',
            'created_at' => '创建时间',
        ],
    ],

    'confirm' => [
        'delete_subscription' => '删除此订阅？',
    ],

    'ipaymu_return' => [
        'invoice_not_found' => '无法将此付款匹配到发票。',
        'paid' => '已收到付款，发票已标记为已付款。',
        'pending' => '付款正在处理中。如果状态尚未更新，请稍后刷新页面。',
        'failed' => '付款失败或被拒绝。',
        'cancelled' => '付款已取消。',
        'expired' => '付款会话已过期。',
    ],

    'checkout' => [
        'title' => '付款',
        'invoice_summary' => '发票 :number',
        'amount' => '应付金额',
        'subtotal' => '服务价格',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'payment_fee' => '支付手续费',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'payment_fee_note' => '该费用支付给提供商，并计入您的总额。',
        'total_due' => '应付总额',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'fee_estimate_hint' => '预计手续费——继续付款时将确认最终金额。',
        'fee_percent_basis' => '服务价格的 :percent',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'fee_at_checkout_hint' => '选择付款方式后将加收提供商手续费。',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'routing_updated' => '付款方式已更新，手续费可能已变化。',
        'channel_unavailable' => ':channel 已不再可用。',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'fee_updated' => ':channel 的手续费已从 :old 更新为 :new。',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'selected_channel_unavailable' => '您选择的付款方式已不再可用，请选择其他方式。',
        'pay_now' => '立即付款',
        'snap_loading' => '正在打开付款页面…',
        'snap_failed' => '无法开始结账',
        'started' => '结账会话已创建。',
        'paid' => '此发票已付款。',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'not_payable' => '此发票当前状态无法付款。',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'unsupported_gateway' => '发票的支付网关不支持托管结账。',
        'select_channel' => '付款方式',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'cheap_alt_hint' => '使用 :channel 付款可节省手续费（结账时扫描二维码）。',
        'pay_with_channel' => '继续付款',
        'channel_loading' => '正在创建付款…',
        'channel_ready' => '按照以下说明完成付款。',
        'channel_failed' => '无法开始渠道结账',
        'confirm_paid' => '我已付款',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'confirm_paid_help' => '如果您已在此页面之外完成付款（例如现金或银行转账），请点击此处重新检查发票状态。',
        'change_channel' => '更改付款渠道',
        'no_channels' => '此发票没有可用的付款方式。',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'permission_required' => '您无权发起付款。请联系管理员授予“发起付款”权限。',
        'synced' => '付款状态已同步。',
        'syncing' => '同步中…',
        'sync_pending' => '付款仍在处理中。',
        'sync_failed' => '无法同步付款状态。',
    ],

    'instructions' => [
        'title' => '付款说明',
        'expires_at' => '请在 :time 前付款',
        'qr_string' => '二维码内容',
        'bank' => '银行',
        'va_number' => '虚拟账户',
        'account_name' => '账户名称',
        'open_ewallet' => '打开电子钱包应用',
        'copy' => '复制',
        'copied' => '已复制到剪贴板',
        'copy_failed' => '复制失败',
        'pending_hint' => '支付提供商确认付款后，状态会自动更新。',
    ],

    'channels' => [
        'title' => '支付渠道',
        'list_title' => '支付渠道列表',
        'updated' => '支付渠道已更新。',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'route_updated' => '支付渠道路由已更新。',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'routes_title' => '网关路由',
        // curated at 2026-09-22T16:42:54+07:00 — zh_CN curation wave
        'routes_lead' => '结账会先尝试主要或唯一的路由，再尝试备用路由。同一角色内，优先级数字较小的路由优先尝试。未启用的路由或缺少网关凭证的路由会被跳过。',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'empty' => '尚未配置支付渠道。请在 PaymentGatewaySeeder 之后运行 php artisan payments:sync-routing-policy。',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'no_routes' => '此渠道未绑定任何网关路由。',
        'no_results' => '没有匹配搜索条件的渠道。',
        'search_label' => '搜索渠道',
        'search_placeholder' => '按名称、代码或类型搜索…',
        'inactive_channel' => '已从结账中隐藏',
        'primary_route' => '优先尝试',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'priority_hint' => '数字越小，同一路由角色内的优先级越高。策略管理的优先级无法在此编辑。',
        'priority_autosave_hint' => '已自动保存',
        'visible_count' => '{0} 没有渠道|{1} 可见 :count 个渠道|[2,*] 可见 :count 个渠道',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'route_count' => '{0} 没有路由|{1} :count 条路由|[2,*] :count 条路由',
        'status' => [
            'active' => '启用',
            'inactive' => '停用',
        ],
        'kinds' => [
            'qris' => 'QRIS',
            'va' => '虚拟账户',
            'ewallet' => '电子钱包',
        ],
        'intro' => [
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'title' => '渠道结账路由的工作方式',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'lead' => '客户选择逻辑渠道（QRIS、银行虚拟账户、电子钱包）。Monolith 随后按策略顺序（先路由角色，再优先级）尝试网关路由，直到创建付款说明。',
            'steps' => [
                [
                    'title' => '启用渠道',
                    // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
                    'body' => '未启用的渠道会从结账中隐藏。渠道关闭时，路由仍可保持配置。',
                ],
                [
                    // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
                    'title' => '了解路由角色',
                    // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
                    'body' => '主要或唯一的路由会在备用路由之前尝试。同一角色内，优先级 10 的路由先于 20。手续费和结算信息来自 Routing_Price.yaml。',
                ],
                [
                    // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
                    'title' => '结账时的备用路由',
                    // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
                    'body' => '如果所有可用路由都失败或缺少凭证，结账可能会提供托管 Snap 或 iPaymu 重定向。',
                ],
            ],
        ],
        'stats' => [
            'channels_active' => '启用的渠道',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'routes_active' => '启用的路由',
            'checkout_hint' => '结账行为',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'checkout_body' => '只有至少包含一条可用路由的启用渠道才会显示给付款人。',
        ],
        'columns' => [
            'code' => '代码',
            'display_name' => '显示名称',
            'kind' => '类型',
            'sort_order' => '排序顺序',
            'is_active' => '启用',
        ],
        'route_columns' => [
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'gateway' => '支付网关',
            'provider_type' => '提供商类型',
            'provider_channel' => '提供商渠道',
            'priority' => '优先级',
            'is_active' => '启用',
            'route_role' => '角色',
            'settlement' => '结算',
            // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
            'fee' => '手续费',
            'policy_managed' => '策略管理',
        ],
        'route_roles' => [
            'primary' => '主要',
            'fallback' => '备用',
            'only' => '唯一',
        ],
        'sync_policy' => '同步路由策略',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'sync_policy_hint' => '从 Routing_Price.yaml 重新加载渠道和路由。',
        // curated at 2026-09-22T11:16:10+07:00 — zh_CN curation wave
        'sync_policy_done' => '路由策略已同步（:channels 个渠道，:routes 条路由）。正在重新加载…',
    ],
];
