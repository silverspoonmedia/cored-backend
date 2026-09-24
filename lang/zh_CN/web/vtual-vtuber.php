<?php

return [
    'title' => 'vTuber 资料',
    'hub_title' => 'vTual',
    'breadcrumb' => 'vTuber 资料',
    'section_bio' => '资料',
    'section_about' => '关于',
    'section_channels' => '频道',
    'section_contents' => '内容',
    'tab_channels' => '频道',
    'tab_contents' => '内容',
    'stat_channels' => '频道',
    'stat_contents' => '已索引内容',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'stat_live' => '正在直播',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'slug_label' => '标识',
    'platforms_label' => '平台',
    'subscribers' => ':count 位订阅者',
    'channels_empty' => '尚未关联频道。',
    'contents_empty' => '尚未索引内容。',
    'bio_empty' => '暂无简介。',
    'table_caption_channels' => '平台频道',
    'table_caption_contents' => '已索引内容',
    'columns' => [
        'avatar' => '头像',
        'platform' => '平台',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'handle' => '账号',
        'display_name' => '显示名称',
        'link' => '链接',
        'thumbnail' => '缩略图',
        'title' => '标题',
        'type' => '类型',
        'status' => '状态',
        'published_at' => '发布时间',
    ],
    'content_type' => [
        'live' => '直播',
        'archive' => '归档',
        'vod' => '点播',
    ],
    'content_status' => [
        'scheduled' => '已排期',
        'live' => '直播中',
        'ended' => '已结束',
        'archived' => '已归档',
        'uploaded' => '已上传',
        'unknown' => '未知',
    ],
    'open_link' => '打开',
    'claim_button' => '认领',
    'claim_page_title' => '认领此资料',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_page_subtitle' => '在频道简介中放入一次性令牌，即可证明您控制此资料关联的平台频道。',
    'claim_steps_heading' => '认领流程',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_step_1' => '请从此资料中选择一个证明频道（见下方列表）。',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_step_2' => '请为此资料和所选证明频道发起认领，您将获得一个令牌（前缀 vtual-claim-…）。',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_step_3' => '请将完整令牌粘贴到 YouTube 频道简介或 Twitch 频道简介中。',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_step_4' => '请执行验证：系统会通过生产环境资料解析器重新获取频道描述。',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    // curated at 2026-09-22T20:30:00+07:00 — brand casing normalized to vTual
    'claim_step_5' => '验证成功后，您将成为该资料的所有者（成员所有者 + vTual vTuber 产品角色）。',
    'claim_channels_heading' => '此资料中的频道',
    'claim_back_to_profile' => '返回资料',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_no_channels_note' => '尚未关联频道。工作人员必须先为此资料注册频道，您才能认领。',
    'claim_verify_with_channel' => '使用此频道验证（简介令牌）',
    'claim_verify_with_discord' => '使用 Discord 认领',
    'claim_discord_connect_cta' => '连接 Discord 以验证',
    'claim_discord_verify_button' => '使用 Discord 验证',
    'claim_discord_verified' => 'Discord 验证完成。',
    'claim_discord_verify_failed' => 'Discord 验证未完成。',
    'claim_missing_channel' => '选择要认领的频道。',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_discord_panel_hint' => '我们会将此 vTuber 资料中的任意启用频道与 Discord 资料中关联的已验证 YouTube 或 Twitch 账户进行匹配，而不仅仅是您为简介令牌选择的证明频道。',
    'claim_verify_button' => '验证',
    'claim_verify_queued' => '验证已排队，请稍后重新加载此页面查看结果。',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_verify_queue_notice' => '验证在共享批次队列中运行：当待处理验证达到 50 个，或最早的等待窗口结束（最长 15 分钟）后执行。请重新加载此页面查看更新。',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_token_label' => '您的认领令牌',
    'claim_attempts_remaining' => '剩余尝试次数',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_blocked_until' => '在 :until 之前无法认领。',
    'claim_queue_status_label' => '队列状态',
    'claim_queue_status_pending' => '已排队',
    'claim_queue_status_processing' => '处理中',
    'claim_active_heading' => '当前认领',
    'claim_proof_channel_label' => '证明频道',
    'claim_cancel' => '取消认领',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_missing_active' => '没有进行中的认领，无法执行此操作。',
    'claim_realtime_connected' => '实时更新',
    'claim_realtime_connecting' => '连接中…',
    'claim_realtime_degraded' => '实时更新不可用。验证仍可排队，请手动重新加载页面查看批次结果。',
    'claim_verify_queue_notice_realtime' => '验证在共享批次队列中运行（最多 50 个待处理项目或 15 分钟等待窗口）。处理完成后，结果会自动显示在此处。',
    'claim_verify_queue_notice_degraded' => '验证在共享批次队列中运行。验证后请手动重新加载此页面查看结果（不会自动轮询）。',
    'claim_progress_processing' => '正在检查频道简介…',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_progress_verified' => '认领已验证，您现在就是该资料的所有者。',
    'claim_progress_failed' => '简介中未找到令牌。更新简介后重试。',
    // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
    'claim_progress_max_attempts' => '已达到最大验证尝试次数，此资料已无法认领。',
];
