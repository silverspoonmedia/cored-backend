<?php

return [
    'title' => '孤立 vTuber',
    'breadcrumb' => '孤立 vTuber',
    'list_title' => '孤立 vTuber 资料',
    // curated at 2026-09-22T11:11:59+07:00 — zh_CN curation wave
    'subtitle' => '由工作人员预先创建的、尚无所有者的资料。已认领的资料不会显示在此列表中。',
    'add_button' => '添加孤立 vTuber',
    'create_title' => '添加孤立 vTuber',
    'edit_title' => '编辑孤立 vTuber',
    'form' => [
        'display_name' => '显示名称',
        // curated at 2026-09-22T11:11:59+07:00 — zh_CN curation wave
        'slug' => '标识',
        'slug_hint' => '可选。仅允许小写字母、数字和连字符。',
        'bio' => '简介',
        'avatar_url' => '头像 URL',
        'submit_create' => '创建孤立资料',
        'submit_update' => '保存更改',
    ],
    'datatable' => [
        'empty' => '暂无孤立 vTuber 资料。',
        'zero_records' => '没有匹配的资料。',
        'confirm_delete' => '归档此孤立资料？归档后将无法认领。',
        'columns' => [
            'display_name' => '显示名称',
            // curated at 2026-09-22T11:11:59+07:00 — zh_CN curation wave
            'slug' => '标识',
            'channels' => '频道',
            'created_at' => '创建时间',
            'actions' => '操作',
        ],
    ],
    'channels' => [
        'section_title' => '平台频道',
        // curated at 2026-09-22T11:11:59+07:00 — zh_CN curation wave
        'section_help' => '添加要批量解析的频道资料（不会立即调用 API）。当每个平台排队满 50 个频道，或等待 15 分钟后，将自动解析。',
        'avatar' => '头像',
        'platform' => '平台',
        'profile' => '频道资料',
        'profile_help' => '平台频道 ID、@handle、login 或资料 URL。',
        'profile_help_youtube' => 'YouTube：UC 频道 ID、@handle 或 youtube.com/… 资料 URL。',
        'profile_help_twitch' => 'Twitch：数字用户 ID、login 或 twitch.tv/… 资料 URL。',
        'profile_placeholder_youtube' => 'UC… / @handle / https://youtube.com/@handle',
        'profile_placeholder_twitch' => '12345678 / login / https://twitch.tv/login',
        'profile_or_id' => '资料 / 外部 ID',
        // curated at 2026-09-22T11:11:59+07:00 — zh_CN curation wave
        'handle' => '账号',
        'status' => '状态',
        'add_button' => '添加频道',
        'table_caption' => '孤立 vTuber 频道',
        'empty' => '尚未注册频道。',
        'status_pending' => '等待解析',
        // curated at 2026-09-22T11:11:59+07:00 — zh_CN curation wave
        'status_active' => '已启用',
        'status_disconnected' => '已断开连接',
        'status_archived' => '已归档',
    ],
];
