<?php

return [
    'title' => '设置',
    // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
    'subtitle' => '通过管理界面维护的运行�
�置。',
    // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
    'read_only' => '您对这些设置只有查看权限。',
    'source' => '当前来源：:source',
    'groups' => [
        'platform' => '平台',
        'notification_audiences' => '通知受众',
        'currencies' => '货币',
    ],
    'fields' => [
        // Platform — JWT
        'platform.jwt.ttl' => 'JWT 访问令牌有效期（分钟）',
        'platform.jwt.refresh_ttl' => 'JWT 刷新窗口（分钟）',
        'platform.jwt.remember_ttl' => 'JWT“记住我”有效期（分钟）',
        'platform.jwt.refresh_skew' => 'JWT 静默刷新提前时间（分钟）',
        'platform.jwt.blacklist_grace_period' => 'JWT 黑名单宽限期（秒）',
        // Platform — auth / 2FA
        'platform.auth.default_role' => '新用户的默认角色',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.auth.verification_expire' => '电子邮件验证链接有效期（分钟）',
        'platform.two_factor.window' => '2FA TOTP 验证窗口',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.two_factor.issuer' => '2FA 签发�
名称（验证器应用）',
        // Platform — hCaptcha
        'platform.hcaptcha.enabled' => '启用 hCaptcha',
        // Platform — notifications
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.notifications.delivery' => '通知投递方式',
        'platform.notifications.polling_interval' => '通知轮询间隔（秒）',
        // Platform — error log
        'platform.errorlog.enabled' => '启用持�
化错误日志',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.errorlog.clean_after_days' => '错误日志保留天数',
        'platform.errorlog.max_message_length' => '错误日志最大消息长度',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.errorlog.max_trace_length' => '错误日志最大堆栈跟踪长度',
        'platform.errorlog.max_context_json_length' => '错误日志最大上下文 JSON 长度',
        // Platform — activity log
        'platform.activitylog.enabled' => '启用活动日志',
        'platform.activitylog.buffer_enabled' => '启用活动日志缓冲',
        // Platform — localization
        // curated at 2026-09-22T13:09:56+07:00 — zh_CN curation wave
        'platform.localization.locale_cookie' => '语言环境 Cookie 名称',
        'identity.discord.connections_snapshot_max_age_minutes' => 'Discord 连接快�
�最大有效期（分钟）',
        // curated at 2026-09-22T13:09:56+07:00 — zh_CN curation wave
        'platform.localization.locale_cookie_minutes' => '语言环境 Cookie 有效期（分钟）',
        // Platform — payment
        'platform.payment.channel_fee_bearer' => '支付渠道费用承�
方',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.payment.reporting_timezone' => '支付报表时区',
        'platform.payment.prefer_channel_checkout' => '优�
�使用渠道原生结账',
        'platform.payment.default_buyer_phone' => '默认买家电话（iPaymu 备用值）',
        // Platform — mail identity
        'platform.mail.from_address' => '邮件发件地址',
        'platform.mail.from_name' => '邮件发件人名称',
        // Platform — service
        'platform.service.grace_days' => '服务宽限天数',
        'platform.service.settlement_currency' => '结算货币',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.service.purchase_max_quantity' => '服务购买数量上限',
        // Platform — inference fallbacks
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.inference.default_provider_slug' => '默认推理提供商标识',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.inference.default_input_per_million_usd' => '默认输�
�费率（每 100 万 token 的美�
�价格）',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.inference.default_output_per_million_usd' => '默认输出费率（每 100 万 token 的美�
�价格）',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.inference.unknown_input_per_million_usd' => '未知模型输�
�费率（每 100 万 token 的美�
�价格）',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.inference.unknown_output_per_million_usd' => '未知模型输出费率（每 100 万 token 的美�
�价格）',
        // Platform — Frankfurter FX
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.frankfurter.cache_ttl' => 'Frankfurter 汇率缓存有效期（秒）',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.frankfurter.timeout' => 'Frankfurter 汇率查询 HTTP �
时（秒）',
        // curated at 2026-09-22T13:09:56+07:00 — zh_CN curation wave
        // curated at 2026-09-22T13:09:56+07:00 — zh_CN curation wave
时（分钟）',
�额（字节）',
�开下载签名有效期（分钟）',
�许的 MIME 类型前缀',
�费下载',
�费下载上限',
�费额度重置时区',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
�）',
�普通处理前的时间（秒）',
�宽限期处理前的时间（秒）',
�宽限模式前的时间（秒）',
�宽限模式前的时间（秒）',
�积压队列前的时间（秒）',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
�费层模型（留空 = 付费模型）',
时（秒）',
�费层模型（留空 = 付费模型）',
时（秒）',
    ],
    'hints' => [
        // curated at 2026-09-22T16:42:56+07:00 — zh_CN curation wave
        'platform.mail.from_address' => '用于发送邮件的�
�局发件地址（传输凭证仍保存在 .env 中）。',
        'platform.payment.default_buyer_phone' => '买家资料没有电话时使用的备用号码（iPaymu 要求）。',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.frankfurter.timeout' => 'Frankfurter 汇率查询的 HTTP �
时时间。',
�费层模型覆盖值；留空则继承付费视觉模型。',
�费层模型覆盖值；留空则继承付费思考模型。',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
限制通过并发上限执行）。',
        // curated at 2026-09-22T13:09:56+07:00 — zh_CN curation wave
        'platform.jwt.refresh_skew' => '在访问令牌过期前的这几分钟刷新令牌。',
        // curated at 2026-09-22T16:42:56+07:00 — zh_CN curation wave
        'platform.notifications.delivery' => '实时模式使用 WebSocket，需要�
�置 PUSHER_* / 广播凭证；否则通知图标会回退到 HTTP 轮询。',
        'platform.payment.channel_fee_bearer' => '支付渠道费用承�
方：商户或客户。',
        'platform.inference.default_provider_slug' => '请求未指定提供商时使用的备用提供商。',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.inference.default_input_per_million_usd' => '已注册模型在目录中没有价格时使用的临时计费费率。',
        'platform.inference.unknown_input_per_million_usd' => '未注册模型的临时计费费率。',
�普通处理前的等�
秒数。',
秒数。',
�宽限模式前的等�
秒数。',
�宽限模式前的等�
秒数。',
�积压队列前的等�
秒数。',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'identity.discord.connections_snapshot_max_age_minutes' => '存储的 Discord 连接快�
�的有效期；�
过该时长后，若验证未命中就会触发实时 Discord API 刷新。',
    ],
    'options' => [
        'platform' => [
            'notifications' => [
                'delivery' => [
                    'polling' => 'HTTP 轮询',
                    'realtime' => 'WebSocket（实时）',
                ],
            ],
        ],
    ],
];
