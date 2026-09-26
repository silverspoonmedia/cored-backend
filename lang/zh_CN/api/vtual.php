<?php

return [
    'creator' => [
        'list_success' => '创作者列表已加载。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'owned_list_success' => '您拥有的 vTuber 资料已加载。',
        'show_success' => '创作者信息已加载。',
        'created' => '创作者已创建。',
        'orphan_created' => '孤立创作者已创建（无所有者，可认领）。',
        'updated' => '创作者已更新。',
        'archived' => '创作者已归档。',
        'name_required' => '显示名称为必填项。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'slug_taken' => '此创作者标识已被占用。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'not_found' => '未找到创作者资料。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'self_create_disabled' => '已停用直接创建资料。请认领现有资料，或联系工作人员为您创建。',
    ],
    'orphan' => [
        'list_success' => '孤立创作者列表已加载。',
        'show_success' => '孤立创作者信息已加载。',
        'not_claimable' => '此资料已不再是孤立资料（已被认领或未启用）。',
    ],
    'vtuber' => [
        'show_success' => 'vTuber 资料已加载。',
        'not_found' => '未找到 vTuber 资料，或资料不可用。',
    ],
    'public' => [
        'directory_success' => '公开 vTuber 目录已加载。',
        'show_success' => '公开 vTuber 资料已加载。',
        'contents_success' => '公开内容动态已加载。',
        'landing_success' => '公开落地页数据已加载。',
        'management_show_success' => '公开管理机构资料已加载。',
    ],
    'merge' => [
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'success' => '资料已合并。源资料已归档，数据现归入目标资料。',
        'self' => '不能将资料合并到自身。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'owner_required' => '您必须同时是源资料和目标资料的所有者才能合并。',
        'destination_archived' => '目标资料已归档，无法接收合并内容。',
        'source_archived' => '源资料已归档。',
        'channel_clash' => '两个资料拥有相同的平台频道（:external_id）。请先归档或移除其中一个频道。',
    ],
    'claim' => [
        'claimable_success' => '可认领创作者列表已加载。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'list_success' => '您的认领记录已加载。',
        'show_success' => '认领记录已加载。',
        'started' => '认领已开始。请将令牌放入频道简介，然后进行验证。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'verified' => '认领已验证，您现在就是所有者。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'verify_queued' => '验证已排队。批次就绪后会自动执行（待处理达到 50 个，或等待窗口结束后）。',
        'verify_already_queued' => '此认领的验证已经排队。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'blocked' => '当前无法认领此资料，请在限制期结束后重试。',
        'cancelled' => '认领已取消。',
        'accepted_anyway' => '认领已接受，未执行简介检查（工作人员操作）。',
        'not_claimable' => '此创作者不可认领（已有所有者或未启用）。',
        'channel_mismatch' => '证明频道必须属于正在认领的创作者。',
        'token_not_found' => '频道描述或简介中未找到认领令牌。请添加令牌后重试。',
        'not_pending' => '此认领不处于待处理状态。',
        'already_owned' => '此创作者已有所有者，认领无法完成。',
        'expired' => '此认领已过期，请重新开始认领。',
        'max_attempts' => '此认领的验证尝试次数过多。请重新开始认领，或等待工作人员协助。',
        'cooldown' => '请等待 :seconds 秒后再次验证。',
        'accept_anyway_disabled' => '此环境已禁用“无论如何接受”。',
        'discord_verify_disabled' => '此环境已禁用 Discord 认领验证。',
        'discord_not_connected' => '使用 Discord 验证前，请从“已连接账户”连接 Discord。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'discord_connections_failed' => '无法加载您关联的 Discord 账户，请尝试重新连接 Discord。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'discord_no_match' => '在您的 Discord 资料中未找到匹配的已验证 YouTube 或 Twitch 账户。',
        'discord_verify_unsupported_platform' => '此平台不支持 Discord 验证。',
        'channel_missing' => '缺少认领证明频道。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'max_pending' => '您已有 :max 个待处理认领。请先取消一个或完成验证。',
        'pending_success' => '待处理认领已加载。',
        'superseded' => '此创作者的另一项认领已完成，此认领已关闭。',
    ],
    'management' => [
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'list_success' => '管理机构列表已加载。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'show_success' => '管理机构已加载。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'created' => '管理机构已创建。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'updated' => '管理机构已更新。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'deleted' => '管理机构已归档。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'name_required' => '管理机构名称为必填项。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'slug_taken' => '此管理机构标识已被占用。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'creator_assigned' => '创作者已分配到管理机构。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'creator_unassigned' => '创作者已从管理机构解除分配。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'assignment_not_found' => '未找到有效的管理机构分配。',
        'not_found' => '未找到管理机构资料或不可用。',
        'members_list_success' => '管理机构成员已加载。',
        'member_added' => '成员已加入管理机构。',
        'member_updated' => '成员角色已更新。',
        'member_already_exists' => '该用户已是管理机构成员。',
        'member_not_found' => '未找到管理机构成员。',
        'role_invalid' => '成员角色或用户无效。',
        'cannot_edit_self' => '不能修改或移除自己的成员身份。',
        'last_owner' => '管理机构必须至少保留一名所有者。',
        'invites_list_success' => '管理机构邀请已加载。',
        'invite_sent' => '邀请已发送。',
        'invite_cancelled' => '邀请已取消。',
        'invite_accepted' => '邀请已接受。欢迎加入管理机构。',
        'invite_declined' => '邀请已拒绝。',
        'invite_already_pending' => '该邮箱已有待处理的邀请。',
        'invite_not_actionable' => '此邀请已无法更改。',
        'invite_expired' => '此邀请已过期或无效。',
        'invite_wrong_account' => '此邀请属于其他账户。',
        'invite_user_not_found' => '没有使用该邮箱地址注册的用户。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'owner_invite_not_claimable' => '该管理机构已有所有者。所有者邀请仅在其未被认领时可用。',
        'creator_invite_mode_invalid' => '创作者邀请模式必须是 "existing" 或 "new"。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'creator_invite_not_owner' => '被邀请的账户不是所选 vTuber 资料的所有者。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'creator_already_linked' => '该 vTuber 资料已关联到此管理机构。',
        'creator_invite_name_required' => '新 vTuber 资料的显示名称为必填项。',
        'events_list_success' => '管理机构活动已加载。',
        'event_created' => '活动已创建。',
        // curated at 2026-09-24T18:10:00+07:00 — zh_CN curation wave: vTual management event edit
        'event_updated' => '活动已更新。',
        'event_cancelled' => '活动已取消。',
        'event_not_found' => '未找到该管理机构的活动。',
        'event_not_actionable' => '此活动已无法更改。',
    ],
    'mail' => [
        'invite_subject' => '邀请加入 :management',
        'creator_invite_subject' => '来自 :management 的 vTuber 资料邀请',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'creator_invite_line' => '您已被邀请将您的 vTuber 资料关联到 :management。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'invite_greeting' => '您好！',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'invite_line' => '您已被邀请以 :role 身份加入 :management。',
        'invite_action' => '查看邀请',
        'invite_expiry' => '此邀请将于 :date 过期。',
    ],
    'channel' => [
        'list_success' => '频道列表已加载。',
        'show_success' => '频道信息已加载。',
        'created' => '频道已注册并验证。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'seeded' => '频道已解析并注册（工作人员预先创建，未通过简介认领）。',
        'registered_pending' => '频道已加入批量解析队列（不会立即调用 API）。',
        'refreshed' => '频道资料已从平台刷新并验证。',
        'refresh_queued' => '频道刷新已排队（与其他待处理请求批量执行）。',
        'archived' => '频道已归档。',
        'deleted' => '待处理频道已删除。',
        'pending_not_archivable' => '待处理频道无法归档，请改为删除。',
        'not_pending_delete' => '只有待处理频道才能通过此方式删除。',
        'restored' => '频道已恢复为启用状态。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'promoted' => '频道已提升为公开频道，资料刷新与内容回填已排队。',
        'not_private_discovered' => '只有已发现的私有频道可以提升。',
        'promote_not_discovered' => '只有 Discord 发现的频道可以通过此操作提升。',
        'promote_unsupported_platform' => '此平台不支持提升操作。',
        'not_archived' => '频道未归档。',
        'lifecycle_cooldown' => '请等待 :seconds 秒后再次归档或恢复此频道。',
        'lifecycle_rate_limited' => '频道归档/恢复操作过于频繁，请稍后重试。',
        'archived_list_success' => '已归档频道列表已加载。',
        'platform_unknown' => '未知平台。',
        'platform_disabled' => '此平台尚未启用。',
        'external_id_required' => '外部频道 ID 为必填项。',
        'external_id_taken' => '此平台 ID 对应的频道已存在。',
        'duplicate_on_creator' => '此平台频道已在此 vTuber 资料中注册。',
        'profile_required' => '频道资料为必填项（ID、handle、login 或 URL）。',
        'profile_invalid' => '无法解析频道资料。请使用平台 ID、@handle/login 或资料 URL。',
        'profile_platform_mismatch' => '此资料 URL 属于其他平台。请切换平台下拉选项，或粘贴匹配的资料。',
        'profile_not_found' => '在该平台上未找到此资料对应的频道。',
        'profile_unsupported_platform' => '此平台不支持资料解析。',
    ],
    'channel_claim' => [
        'list_success' => '频道认领记录已加载。',
        'show_success' => '频道认领记录已加载。',
        'started' => '频道认领已开始。请将令牌放入频道简介，然后进行验证。',
        'verified' => '频道已验证并绑定到此资料。',
        'cancelled' => '频道认领已取消。',
        'accepted_anyway' => '频道已接受，未执行简介检查（工作人员操作）。',
        'token_not_found' => '频道描述或简介中未找到认领令牌。请添加令牌后重试。',
        'not_pending' => '此频道认领不处于待处理状态。',
        'expired' => '此频道认领已过期，请重新开始认领。',
        'max_attempts' => '此频道认领的验证尝试次数过多。请重新开始认领，或等待工作人员协助。',
        'cooldown' => '请等待 :seconds 秒后再次验证。',
        'accept_anyway_disabled' => '此环境已禁用“无论如何接受”。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'pending_other' => '已有其他用户对此平台频道提交了待处理的认领。',
        'discord_verify_disabled' => '频道认领的 Discord 验证已禁用。',
        'discord_verify_unsupported_platform' => '此平台不支持频道认领的 Discord 验证。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'discord_not_connected' => '验证前，请先在“个人资料 → 已连接账户”中连接 Discord。',
        'discord_connections_failed' => '无法加载 Discord 连接，请重试或使用简介令牌验证。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'discord_no_match' => '没有与此平台频道匹配的已验证 Discord 连接。',
        'discord_verified' => '频道已通过 Discord 验证并绑定到此资料。',
    ],
    'link' => [
        'list_success' => '外部链接已加载。',
        'created' => '外部链接已添加。',
        'updated' => '外部链接已更新。',
        'deleted' => '外部链接已移除。',
        'url_required' => '链接 URL 为必填项。',
        'not_creator' => '此链接不属于 vTuber 资料。',
    ],
    'content' => [
        'external_id_required' => '平台内容 ID 为必填项。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'appearance_primary_via_set' => '主频道不能作为出场频道附加。请使用 setPrimary。',
        'appearance_role_invalid' => '内容出场角色无效。',
        'appearance_platform_mismatch' => '出场频道必须与内容使用同一平台。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'appearance_already_primary' => '此频道已经是内容的主频道。',
        'appearance_already_attached' => '此频道已经作为出场频道关联到内容。',
        'appearance_not_found' => '未找到此频道对应的内容出场记录。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'cannot_detach_primary' => '无法解除主频道。请先设置其他主频道。',
        'fetch_platform_unsupported' => '不支持获取平台 :platform 的内容。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'fetch_channel_not_active' => '获取内容需要频道处于启用状态。',
        'fetch_video_channel_mismatch' => '视频 :video 不属于频道 :channel。',
    ],
    'platform' => [
        'list_success' => '平台列表已加载。',
    ],
    'credential' => [
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'list_success' => '平台凭证列表已加载。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'show_success' => '平台凭证已加载。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'created' => '平台凭证已创建。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'updated' => '平台凭证已更新。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'deleted' => '平台凭证已删除。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'revoked' => '平台凭证已撤销。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'label_required' => '凭证名称为必填项。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'label_taken' => '此平台已使用该凭证名称。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'payload_invalid' => '凭证密钥内容无效。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'payload_missing' => '此凭证类型需要密钥字段 :field。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'payload_unknown_key' => '此凭证类型不允许使用密钥字段 :field。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'test_success' => '凭证测试成功，凭证已启用。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'test_missing_secret' => '凭证密钥不完整，无法测试。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'test_unsupported' => '此平台和凭证类型没有可用的测试探针。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'test_not_allowed_status' => '无法测试已撤销的凭证。',
        'test_youtube_ok' => 'YouTube API 密钥已接受。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'test_youtube_empty' => 'YouTube API 未针对探针 ID 返回频道项。',
        'test_twitch_ok' => 'Twitch 应用令牌已生成，Helix 探针成功。',
        'test_bearer_ok' => '平台探针已接受 Bearer 令牌。',
        'test_bearer_ok_no_probe' => 'Bearer 令牌已存在，此平台已跳过实时探针。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'status_active_requires_test' => '请使用“测试”操作将凭证标记为启用。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'revoke_requires_active' => '只有已启用的凭证才能撤销。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'delete_requires_revoke' => '删除凭证前，请先撤销凭证。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'not_active' => '凭证未启用。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'app_token_oauth_only' => '应用访问令牌仅适用于 oauth_app 凭证。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'app_token_twitch_only' => '应用访问令牌生成功能仅针对 Twitch 实现。',
    ],
    'policy' => [
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'denied' => '您无权执行此操作。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'access_denied' => '您不能访问此 vTual 资源。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'mutate_denied' => '您不能修改此 vTual 资源。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'archive_denied' => '只有该管理机构的所有者或管理员成员才能归档。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'members_denied' => '您不能管理此资源的成员。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'assign_denied' => '您不能为此管理机构分配创作者。',
    ],
    'integrations' => [
        'unsupported_operation' => '平台 :platform 不支持操作 :operation。',
        'platform_disabled' => '平台 :platform 已禁用或不可用。',
        'platform_unknown' => '未知平台 :platform。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'no_active_credential' => '平台 :platform 没有已启用的凭证，请先创建并测试一个凭证。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'credential_required' => '平台客户端未绑定凭证。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'missing_api_key' => 'YouTube 凭证缺少 api_key。',
        // curated at 2026-09-22T11:29:04+07:00 — zh_CN curation wave
        'missing_client_id' => 'Twitch 凭证缺少 client_id。',
        'ids_required' => '至少需要一个 :label。',
        'batch_too_large' => ':label 过多（:count），每个请求最多 :max 个。',
        'batch_max_invalid' => '批量上限配置无效。',
        'invalid_part' => '不允许使用 YouTube :resource 部分“:part”。',
        'parts_required' => '至少需要一个 YouTube :resource 部分。',
        'handle_required' => '频道 handle 或 login 为必填项。',
        'youtube_channel_id_required' => '需要有效的 YouTube 频道 ID（UC…）。',
        'search_event_type_invalid' => '搜索事件类型必须为 live、upcoming 或 completed。',
        'feed_fetch_failed' => 'YouTube feed 请求失败（HTTP :status）。',
        'feed_parse_failed' => '无法解析 YouTube feed 响应。',
        'upstream_error' => '平台 :platform 请求失败（HTTP :status）。',
        'upstream_error_detail' => '平台 :platform 请求失败（HTTP :status）：:detail',
        'upstream_connection_error' => '平台 :platform 连接失败。',
        // curated at 2026-09-25T21:30:00+07:00 — upstream retry/quota ops log lines
        'upstream_retry_scheduled' => '平台 :platform 请求失败（HTTP :status），将在 :delay 毫秒后进行第 :attempt 次重试。',
        'upstream_quota_exhausted' => '平台 :platform 配额已用尽（HTTP 429）。请在配额重置后重试。',
        'probe_success' => '平台 API 探针已完成。',
        'probe_failed' => '平台 API 探针意外失败。',
        'probe_ids_required' => '此方法至少需要一个 ID。',
        'probe_unknown_method' => '未知探针方法 :method。',
    ],
];
