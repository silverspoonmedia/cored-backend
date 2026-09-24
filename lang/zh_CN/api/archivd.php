<?php

return [
    'root_label' => '我的文件',
    'browse_success' => '文件列表已加载。',
    'folder_created' => '文件夹已创建。',
    'folder_renamed' => '文件夹已重命名。',
    'folder_moved' => '文件夹已移动。',
    'folder_deleted' => '文件夹已移入回收站。',
    'folder_restored' => '文件夹已恢复。',
    'folder_force_deleted' => '文件夹已永久删除。',
    'file_show_success' => '文件已加载。',
    'file_renamed' => '文件已重命名。',
    'file_moved' => '文件已移动。',
    'file_deleted' => '文件已移入回收站。',
    'file_restored' => '文件已恢复。',
    'file_force_deleted' => '文件已永久删除。',
    'bulk_visibility_updated' => '可见性已更新。',
    'bulk_files_deleted' => '所选文件已移入回收站。',
    'bulk_folders_deleted' => '所选文件夹已移入回收站。',
    'download_ready' => '下载 URL 已准备就绪。',
    'download_unavailable' => '此存储磁盘无法提供下载 URL。',
    'visibility_updated' => '可见性已更新。',
    'invalid_visibility' => '可见性值无效。',
    // curated at 2026-09-22T16:42:53+07:00 — zh_CN curation wave
    'public_share_requires_paid' => '公开链接分享需要有效的付费 Archivd 方案。',
    // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
    'public_share_blocked_hash' => '此文件已被合规策略阻止，无法公开分享。',
    'public_share_not_allowed' => '此工作区中的项目不允许公开分享。',
    'blocklist_saved' => '哈希已添加到阻止列表。',
    'blocklist_removed' => '哈希已从阻止列表移除。',
    // curated at 2026-09-21T15:32:00+07:00 — new key (blocklist list endpoint had no message)
    'blocklist_loaded' => '哈希阻止列表已加载。',
    'share_not_found' => '此共享项目不可用。',
    'search_success' => '搜索结果已加载。',
    'trash_success' => '回收站已加载。',
    'quota_success' => '配额已加载。',
    'quota_exceeded' => '已超出存储配额。',
    // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
    'quota_grace_blocked' => '您的订阅处于宽限期。续费后才能再次上传文件。',
    'folder_not_found' => '未找到文件夹。',
    'file_not_found' => '未找到文件。',
    'owner_not_found' => '未找到所有者。',
    'folder_name_taken' => '此处已存在同名文件夹。',
    'file_name_taken' => '此处已存在同名文件。',
    // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
    'folder_cycle' => '无法将文件夹移动到其自身或其子文件夹中。',
    'policy' => [
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'denied' => '您无权执行此操作。',
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'cross_user_denied' => '您不能访问其他用户的文件。',
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'hierarchy_view' => '您不能查看此用户的文件。',
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'hierarchy_mutate' => '您不能修改此用户的文件。',
    ],
    'workspace' => [
        'list_success' => '工作区已加载。',
        'show_success' => '工作区已加载。',
        'created' => '工作区已创建。',
        'archived_success' => '工作区已归档。',
        'members_success' => '成员列表已加载。',
        'invite_sent' => '邀请已发送。',
        'invite_accepted' => '邀请已接受。',
        'role_updated' => '成员角色已更新。',
        'member_removed' => '成员已移除。',
        'activity_success' => '工作区活动已加载。',
        'not_found' => '未找到工作区。',
        'archived' => '此工作区已归档。',
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'access_denied' => '您不能访问此工作区。',
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'mutate_denied' => '您不能修改此工作区中的文件。',
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'members_denied' => '您不能管理此工作区的成员。',
        // curated at 2026-09-22T16:42:53+07:00 — zh_CN curation wave
        'requires_paid' => '协作工作区需要有效的付费 Archivd 方案（主方案或附加组件）。',
        // curated at 2026-09-22T16:42:53+07:00 — zh_CN curation wave
        'owner_in_grace' => '工作区所有者处于宽限期。续费方案后才能使用协作功能。',
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'already_exists' => '您已经拥有一个工作区。',
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'slot_limit' => '自有团队工作区数量已达上限（:used / :limit）。请归档一个团队，或购买 Team Workspace Slot 附加组件。',
        'name_required' => '工作区名称为必填项。',
        'owner_only' => '只有工作区所有者可以执行此操作。',
        'invalid_role' => '工作区角色无效。',
        'invalid_email' => '邮箱地址无效。',
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'cannot_invite_self' => '您不能邀请自己。',
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'already_member' => '该用户已是工作区成员。',
        'invite_pending' => '此邮箱已有待处理邀请。',
        'invite_not_found' => '未找到邀请。',
        'invite_expired' => '邀请已过期。',
        'invite_not_pending' => '邀请已不再处于待处理状态。',
        'invite_email_mismatch' => '此邀请发送给了其他邮箱地址。',
        'cannot_change_owner_role' => '无法更改所有者角色。',
        'cannot_remove_owner' => '无法移除工作区所有者。',
        'member_not_found' => '未找到工作区成员。',
    ],
    'upload' => [
        'initiated' => '上传会话已创建。',
        'status' => '上传状态已加载。',
        'parts_signed' => '分片 URL 已签名。',
        'completed' => '上传完成，文件已创建。',
        'aborted' => '上传已中止。',
        'not_found' => '未找到上传会话。',
        'not_pending' => '上传会话不处于待处理状态。',
        'expired' => '上传会话已过期。',
        // curated at 2026-09-22T12:07:32+07:00 — zh_CN curation wave
        'already_promoted' => '该上传会话已转为文件。',
        'invalid_path' => '不允许使用此上传路径。',
        'invalid_size' => '文件大小无效。',
        'file_too_large' => '文件超过允许的最大大小。',
        'invalid_name' => '文件名无效。',
        'mime_not_allowed' => '不允许使用此 MIME 类型。',
        'object_missing' => '存储中缺少已上传对象。',
        'multipart_only' => '此操作仅适用于分片上传。',
        'invalid_part_number' => '分片编号无效。',
        'incomplete_parts' => '并非所有分片都已上传。',
        'unsupported_disk' => '配置的存储磁盘不支持直接上传。',
        'proxy_misconfigured' => '上传代理已启用但配置不完整（缺少令牌或磁盘目标别名）。',
        'upstream_failed' => '存储拒绝了上传操作：:detail',
        'upstream_failed_generic' => '存储拒绝了上传操作。请检查 S3/B2 路径样式设置和分片上传状态。',
        'too_many_concurrent' => '并发上传过多。请先完成或中止现有会话。',
    ],
];
