<?php

return [
    'title' => '设置',
    // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
    'subtitle' => '通过管理界面维护的运行配置。',
    // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
    'read_only' => '您对这些设置只有查看权限。',
    'source' => '当前来源：:source',
    'groups' => [
        'platform' => '平台',
        'archivd' => 'Archivd',
        'microstock' => 'Microstock',
        'vtual' => 'vTual',
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
        'platform.two_factor.issuer' => '2FA 签发者名称（验证器应用）',
        // Platform — hCaptcha
        'platform.hcaptcha.enabled' => '启用 hCaptcha',
        // Platform — notifications
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.notifications.delivery' => '通知投递方式',
        'platform.notifications.polling_interval' => '通知轮询间隔（秒）',
        // Platform — error log
        'platform.errorlog.enabled' => '启用持久化错误日志',
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
        'identity.discord.connections_snapshot_max_age_minutes' => 'Discord 连接快照最大有效期（分钟）',
        // curated at 2026-09-22T13:09:56+07:00 — zh_CN curation wave
        'platform.localization.locale_cookie_minutes' => '语言环境 Cookie 有效期（分钟）',
        // Platform — payment
        'platform.payment.channel_fee_bearer' => '支付渠道费用承担方',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.payment.reporting_timezone' => '支付报表时区',
        'platform.payment.prefer_channel_checkout' => '优先使用渠道原生结账',
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
        'platform.inference.default_input_per_million_usd' => '默认输入费率（每 100 万 token 的美元价格）',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.inference.default_output_per_million_usd' => '默认输出费率（每 100 万 token 的美元价格）',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.inference.unknown_input_per_million_usd' => '未知模型输入费率（每 100 万 token 的美元价格）',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.inference.unknown_output_per_million_usd' => '未知模型输出费率（每 100 万 token 的美元价格）',
        // Platform — Frankfurter FX
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.frankfurter.cache_ttl' => 'Frankfurter 汇率缓存有效期（秒）',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.frankfurter.timeout' => 'Frankfurter 汇率查询 HTTP 超时（秒）',
        // Archivd
        'archivd.max_file_size_bytes' => '最大文件大小（字节）',
        // curated at 2026-09-22T13:09:56+07:00 — zh_CN curation wave
        'archivd.max_files_per_batch' => '每批次的最大文件数',
        'archivd.max_concurrent_uploads' => '最大并发上传数',
        'archivd.max_concurrent_parts' => '最大并发分片数',
        'archivd.chunk_size_bytes' => '上传分块大小（字节）',
        'archivd.multipart_threshold_bytes' => '分片上传阈值（字节）',
        'archivd.max_presigned_parts_per_response' => '每次响应的最大预签名分片数',
        'archivd.presign_ttl_minutes' => '预签名 URL 有效期（分钟）',
        'archivd.session_ttl_hours' => '上传会话有效期（小时）',
        // curated at 2026-09-22T13:09:56+07:00 — zh_CN curation wave
        'archivd.stale_upload_grace_hours' => '停滞上传的宽限期（小时）',
        'archivd.orphan_inactivity_minutes' => '孤立分片不活动超时（分钟）',
        'archivd.default_quota_bytes' => '默认存储配额（字节）',
        'archivd.base_owned_workspace_slots' => '基础自有工作区槽位数',
        'archivd.disabled_regions' => '已禁用存储区域',
        'archivd.signed_download_ttl_minutes' => '签名下载 URL 有效期（分钟）',
        'archivd.signed_download_cache_margin_seconds' => '签名下载缓存余量（秒）',
        'archivd.public_download_signature_ttl_minutes' => '公开下载签名有效期（分钟）',
        'archivd.allowed_mime_prefixes' => '允许的 MIME 类型前缀',
        'archivd.download_vault.enabled' => '启用下载保险库代理',
        // Microstock
        'microstock.daily_free.enabled' => '启用每日免费下载',
        'microstock.daily_free.limit_per_user' => '每位用户每日免费下载上限',
        'microstock.daily_free.timezone' => '每日免费额度重置时区',
        'microstock.ai_billing.markup_multiplier' => 'AI 计费加价倍数',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'microstock.ai_billing.estimated_billable_micro_usd_per_asset' => '每项资源预计可计费金额（微美元）',
        'microstock.ai_billing.estimate_thinking_attempt_factor' => '思考尝试估算系数',
        'microstock.ai.image_max_px' => 'AI 图片最大尺寸（px）',
        'microstock.ai.image_jpeg_quality' => 'AI 图片 JPEG 质量',
        'microstock.ai.max_request_image_bytes' => '请求图片最大大小（字节）',
        'microstock.ai.http_retries' => 'AI HTTP 重试次数',
        'microstock.ai.thinking_visual_attempts' => '思考视觉尝试次数',
        'microstock.ai.batch_chunk' => 'AI 批次分块大小',
        'microstock.ai.batch_min_files' => 'AI 批次最少文件数',
        'microstock.ai.phase.normal_after' => 'AI 阶段：进入普通处理前的时间（秒）',
        'microstock.ai.phase.grace_after' => 'AI 阶段：进入宽限期处理前的时间（秒）',
        'microstock.ai.stage.vision_grace_after' => 'AI 阶段：视觉阶段进入宽限模式前的时间（秒）',
        'microstock.ai.stage.thinking_grace_after' => 'AI 阶段：思考阶段进入宽限模式前的时间（秒）',
        'microstock.ai.stage.thinking_backlog_after' => 'AI 阶段：思考任务进入积压队列前的时间（秒）',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'microstock.ai.vision_image.delivery' => '视觉图像投递方式',
        'microstock.ai.vision_image.variant_kind' => '视觉图像变体类型',
        'microstock.ai.vision_image.signed_url_ttl_minutes' => '视觉签名 URL 有效期（分钟）',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'microstock.ai.inference_provider_slug' => 'Microstock 推理提供商标识',
        'microstock.ai.vision.connection' => '视觉 AI 连接（留空 = 默认）',
        'microstock.ai.thinking.connection' => '思考 AI 连接（留空 = 默认）',
        'microstock.ai.vision.model' => '视觉模型',
        'microstock.ai.vision.free_model' => '视觉免费层模型（留空 = 付费模型）',
        'microstock.ai.vision.timeout' => '视觉 HTTP 超时（秒）',
        'microstock.ai.thinking.model' => '思考模型',
        'microstock.ai.thinking.free_model' => '思考免费层模型（留空 = 付费模型）',
        'microstock.ai.thinking.timeout' => '思考 HTTP 超时（秒）',
        'microstock.ai.upstream.window_seconds' => '上游 token 预算窗口（秒）',
        'microstock.ai.upstream.token_budget' => '每个窗口的上游 token 预算',
        'microstock.ai.upstream.max_concurrent_vision' => '最大并发视觉请求数',
        'microstock.ai.upstream.max_concurrent_thinking' => '最大并发思考请求数',
        'microstock.ai.upstream.collection_vision_concurrency' => '集合视觉并发数',
        'microstock.ai.upstream.collection_thinking_concurrency' => '集合思考并发数',
        'microstock.ai.upstream.solo_vision_concurrency' => '单项视觉并发数',
        'microstock.ai.upstream.solo_thinking_concurrency' => '单项思考并发数',
        'microstock.ai.upstream.release_after_seconds' => '上游槽位释放时间（秒）',
        'microstock.csv_disk' => 'CSV 导出磁盘',
        // vTual
        // curated at 2026-09-22T16:42:56+07:00 — zh_CN curation wave
        'vtual.credential_test.http_timeout' => '凭证测试 HTTP 超时（秒）',
        'vtual.credential_test.twitch_token_skew_seconds' => 'Twitch 令牌提前量（秒）',
        'vtual.credential_test.youtube_probe_channel_id' => 'YouTube 探针频道 ID',
        // curated at 2026-09-22T16:42:56+07:00 — zh_CN curation wave
        'vtual.credential.failover.enabled' => '启用凭证故障转移',
        'vtual.credential.failover.auth_fail_threshold' => '故障转移身份验证失败阈值',
        'vtual.credential.failover.skip_minutes' => '故障转移跳过时长（分钟）',
        'vtual.credential.failover.max_retries_per_call' => '每次调用的故障转移最大重试次数',
        'vtual.http.timeout' => 'HTTP 超时（秒）',
        'vtual.claim.ttl_hours' => '认领有效期（小时）',
        'vtual.claim.verify_cooldown_seconds' => '认领验证冷却时间（秒）',
        'vtual.claim.max_attempts' => '认领最大验证尝试次数',
        'vtual.claim.block_hours_after_max_attempts' => '达到最大尝试次数后的认领阻止时长（小时）',
        'vtual.claim.max_pending_per_user' => '每位用户最大待处理认领数',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'vtual.claim.accept_anyway_enabled' => '启用认领的“仍可接受”选项',
        'vtual.claim.discord_verify_enabled' => '启用认领 Discord 验证',
        'vtual.claim.token_prefix' => '认领令牌前缀',
        'vtual.claim.token_bytes' => '认领令牌随机字节数',
        'vtual.channel_claim.ttl_hours' => '频道认领有效期（小时）',
        'vtual.channel_claim.verify_cooldown_seconds' => '频道认领验证冷却时间（秒）',
        'vtual.channel_claim.max_attempts' => '频道认领最大尝试次数',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'vtual.channel_claim.accept_anyway_enabled' => '启用频道认领的“仍可接受”选项',
        'vtual.channel_claim.discord_verify_enabled' => '启用频道认领 Discord 验证',
        'vtual.channel_claim.token_prefix' => '频道认领令牌前缀',
        'vtual.channel_claim.token_bytes' => '频道认领令牌随机字节数',
        'vtual.channel_pool.threshold' => '频道池低阈值',
        'vtual.channel_pool.max_wait_minutes' => '频道池最长等待时间（分钟）',
        'vtual.channel_pool.schedule_every_minutes' => '频道池调度间隔（分钟）',
        'vtual.channel_pool.resync_stale_hours' => '频道池重新同步失效时间（小时）',
        'vtual.channel_pool.resync_enqueue_limit' => '频道池重新同步入队上限',
        'vtual.channel_pool.flush_lock_seconds' => '频道池刷新锁定时间（秒）',
        'vtual.claim_verify_pool.threshold' => '认领验证池低阈值',
        'vtual.claim_verify_pool.max_wait_minutes' => '认领验证池最长等待时间（分钟）',
        'vtual.claim_verify_pool.flush_lock_seconds' => '认领验证池刷新锁定时间（秒）',
        'vtual.content_pool.threshold' => '内容池低阈值',
        'vtual.content_pool.max_wait_minutes' => '内容池最长等待时间（分钟）',
        'vtual.content_pool.flush_lock_seconds' => '内容池刷新锁定时间（秒）',
        'vtual.feed_poll.interval_minutes' => '动态轮询间隔（分钟）',
        'vtual.live_poll.interval_minutes' => '直播 CCV 轮询间隔（分钟）',
        'vtual.websub.lease_seconds' => 'WebSub 租约（秒）',
        'vtual.websub.renew_before_hours' => 'WebSub 到期前续租时间（小时）',
        'vtual.websub.max_body_bytes' => 'WebSub 通知正文最大大小（字节）',
        'vtual.landing.event.title' => '落地页活动标题',
        'vtual.landing.event.starts_at' => '落地页活动开始时间',
        'vtual.landing.event.body' => '落地页活动正文',
        'vtual.content_classification.ai.enabled' => '启用 AI 审查（内容分类）',
        'vtual.content_classification.ai.model' => 'AI 审查模型',
        'vtual.content_classification.ai.max_tokens' => 'AI 审查最大响应 token 数',
        'vtual.content_classification.ai.timeout' => 'AI 审查超时（秒）',
        'vtual.content_classification.ai.min_confidence' => 'AI 审查最低置信度',
        'vtual.content_classification.ai.cache_ttl_hours' => 'AI 审查结果缓存（小时）',
        'vtual.content_classification.ai.connection' => 'AI 审查连接（留空 = 默认）',
    ],
    'hints' => [
        // curated at 2026-09-22T16:42:56+07:00 — zh_CN curation wave
        'platform.mail.from_address' => '用于发送邮件的全局发件地址（传输凭证仍保存在 .env 中）。',
        'platform.payment.default_buyer_phone' => '买家资料没有电话时使用的备用号码（iPaymu 要求）。',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.frankfurter.timeout' => 'Frankfurter 汇率查询的 HTTP 超时时间。',
        'microstock.ai.vision.free_model' => '可选的免费层模型覆盖值；留空则继承付费视觉模型。',
        'microstock.ai.thinking.free_model' => '可选的免费层模型覆盖值；留空则继承付费思考模型。',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'microstock.ai.vision.connection' => '视觉阶段使用的 AI 连接标识（设置 → AI 连接）；留空则使用默认连接。',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'microstock.ai.thinking.connection' => '思考阶段使用的 AI 连接标识（设置 → AI 连接）；留空则使用默认连接。',
        'microstock.ai.upstream.window_seconds' => '上游 token 预算的参考窗口（实际限制通过并发上限执行）。',
        // curated at 2026-09-22T16:42:56+07:00 — zh_CN curation wave
        'vtual.credential_test.youtube_probe_channel_id' => '用于 YouTube Data API 凭证冒烟测试的公开频道 ID。',
        // curated at 2026-09-22T13:09:56+07:00 — zh_CN curation wave
        'platform.jwt.refresh_skew' => '在访问令牌过期前的这几分钟刷新令牌。',
        // curated at 2026-09-22T16:42:56+07:00 — zh_CN curation wave
        'platform.notifications.delivery' => '实时模式使用 WebSocket，需要配置 PUSHER_* / 广播凭证；否则通知图标会回退到 HTTP 轮询。',
        'platform.payment.channel_fee_bearer' => '支付渠道费用承担方：商户或客户。',
        'platform.inference.default_provider_slug' => '请求未指定提供商时使用的备用提供商。',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'platform.inference.default_input_per_million_usd' => '已注册模型在目录中没有价格时使用的临时计费费率。',
        'platform.inference.unknown_input_per_million_usd' => '未注册模型的临时计费费率。',
        'microstock.ai.phase.normal_after' => '资源从快速通道转入普通处理前的等待秒数。',
        'microstock.ai.phase.grace_after' => '宽限期处理开始前的等待秒数。',
        'microstock.ai.stage.vision_grace_after' => '视觉阶段进入宽限模式前的等待秒数。',
        'microstock.ai.stage.thinking_grace_after' => '思考阶段进入宽限模式前的等待秒数。',
        'microstock.ai.stage.thinking_backlog_after' => '思考任务移入积压队列前的等待秒数。',
        // curated at 2026-09-22T16:42:56+07:00 — zh_CN curation wave
        'vtual.credential.failover.enabled' => '连续身份验证失败后切换到下一个凭证。',
        'vtual.claim.accept_anyway_enabled' => '允许用户在验证结果不明确时仍接受认领。',
        'vtual.claim.discord_verify_enabled' => '允许用户通过匹配 Discord 关联的 YouTube/Twitch 账户来验证孤立认领。',
        'vtual.channel_claim.discord_verify_enabled' => '允许资料所有者通过匹配 Discord 关联的 YouTube/Twitch 账户来验证频道附加认领。',
        // curated at 2026-09-22T11:48:38+07:00 — zh_CN curation wave
        'identity.discord.connections_snapshot_max_age_minutes' => '存储的 Discord 连接快照的有效期；超过该时长后，若验证未命中就会触发实时 Discord API 刷新。',
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
