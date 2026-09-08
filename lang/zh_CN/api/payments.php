<?php

return [
    'success' => '付款成功完成。',
    'failed' => '付款失败，请重试。',
    'pending' => '付款正在处理中。',

    'gateways' => [
        'list' => '支付网关列表已成功获取。',
        'show' => '支付网关信息已成功获取。',
        'created' => '支付网关已成功创建。',
        'updated' => '支付网关已成功更新。',
        'deleted' => '支付网关已成功删除。',
        'inactive' => '所选支付网关未启用。',
        'none_active' => '未配置已启用的支付网关。',
    ],

    'invoices' => [
        'list' => '发票列表已成功获取。',
        'show' => '发票信息已成功获取。',
        'checkout_channels' => '结账支付渠道已加载。',
        'checkout_channels_not_payable' => '发票当前不可付款。',
    ],

    'income' => [
        'overview' => '付款收入概览已加载。',
        'invoices' => '付款收入发票已加载。',
    ],

    'subscriptions' => [
        'list' => '订阅列表已成功获取。',
        'show' => '订阅信息已成功获取。',
        'created' => '订阅已成功创建。',
        'updated' => '订阅已成功更新。',
        'deleted' => '订阅已成功删除。',
    ],

    'midtrans' => [
        'snap_created' => 'Snap 交易已创建。',
        'charge_created' => 'Core API 收费请求已创建。',
        'status_retrieved' => '交易状态已获取。',
        'cancel_ok' => '交易已取消。',
        'expire_ok' => '交易已过期。',
        'refund_ok' => '退款已处理。',
        'approve_ok' => '交易已批准。',
        'deny_ok' => '交易已拒绝。',
        'sync_ok' => '发票状态已与 Midtrans 同步。',
        'invoice_not_midtrans' => '发票未关联 Midtrans 网关。',
        'invoice_not_payable' => '发票必须处于待处理或挑战状态才能开始付款。',
        'external_sync_failed' => '开始结账前无法向 Midtrans 验证付款状态。',
        'webhook_invalid_json' => 'JSON 请求正文无效。',
        'webhook_invalid_signature' => '通知签名无效。',
        'webhook_missing_order' => '通知中缺少 order_id。',
        'webhook_ack_no_status' => '通知已确认（无法解析状态）。',
        'webhook_orphan' => '通知已接受（没有匹配的发票）。',
        'webhook_ok' => '通知已处理。',
        'webhook_ping' => 'Midtrans webhook URL 可访问（通知请使用 POST）。',
        'webhook_subscription_ack' => '订阅通知已确认。',
        'open_api_public_key_missing' => '未配置 Midtrans Open API 公钥。',
        'open_api_signature_invalid' => 'Open API 通知的 X-SIGNATURE 无效或无法验证。',
    ],

    'checkout' => [
        'active_session_pending' => '上一次付款会话仍在支付提供商处等待处理。请等待其过期或完成后再切换渠道。',
        'gateway_provider_mismatch' => '发票支付网关与请求的结账提供商不匹配。',
    ],

    'channels' => [
        'list' => '支付渠道列表已成功获取。',
        'show' => '支付渠道信息已成功获取。',
        'updated' => '支付渠道已成功更新。',
        'checkout_created' => '付款说明已创建。',
        'checkout_failed' => '此渠道的所有支付路径均失败。',
        'route_updated' => '支付渠道路径已更新。',
        'not_found' => '未找到支付渠道。',
        'not_available' => '所选支付渠道不可用。',
        'unsupported_gateway' => '此渠道未配置受支持的支付网关。',
        'policy_managed_route' => '此路径由路由策略管理，只能更改启用状态。',
    ],

    'ipaymu' => [
        'snap_created' => 'iPaymu 托管结账会话已创建。',
        'charge_created' => 'iPaymu 直接付款已创建。',
        'status_retrieved' => '已从 iPaymu 获取交易状态。',
        'sync_ok' => '发票状态已与 iPaymu 同步。',
        'invoice_not_ipaymu' => '发票未关联 iPaymu 网关。',
        'invoice_not_payable' => '发票必须处于待处理或挑战状态才能开始付款。',
        'external_sync_failed' => '开始结账前无法向 iPaymu 验证付款状态。',
        'active_payment_exists' => '此发票已有付款会话正在进行。开始新的结账前，请先完成或取消当前会话。',
        'invoice_not_found' => '未找到此订单 ID 对应的 iPaymu 发票。',
        'missing_transaction_id' => '发票尚无 iPaymu 交易 ID，请先完成付款会话。',
        'buyer_required' => '无法从请求、可开票对象或已认证用户资料中解析买家信息。',
        'webhook_invalid_json' => 'JSON 请求正文无效。',
        'webhook_invalid_signature' => '通知签名无效。',
        'webhook_invalid_va' => 'VA 标头与已配置的 iPaymu VA 不匹配。',
        'webhook_ack_no_status' => '通知已确认（无法解析状态）。',
        'webhook_orphan' => '通知已接受（没有匹配的发票）。',
        'webhook_ok' => '通知已处理。',
        'webhook_ping' => 'iPaymu webhook URL 可访问（通知请使用 POST）。',
    ],
];
