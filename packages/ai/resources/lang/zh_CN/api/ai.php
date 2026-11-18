<?php

return [
    'connection' => [
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'list_success' => '已成功获取 AI 连接。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'show_success' => '已成功获取 AI 连接。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'created' => 'AI 连接已创建。请运行测试以激活。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'updated' => 'AI 连接已更新。请再次运行测试以重新激活。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'deleted' => '已成功删除 AI 连接。',
        'default_updated' => '默认 AI 连接已更新。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'slug_required' => '连接标识为必填项。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'slug_taken' => '该连接标识已被使用。',
        'label_required' => '连接名称为必填项。',
        'endpoint_invalid' => '端点必须是有效的 http(s) URL。',
        'test_model_required' => '需要测试模型才能探测此连接。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'payload_invalid' => '已保存的密钥数据无效，请重新输入 API 密钥。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'payload_missing' => '密钥数据缺少必填字段：:field。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'payload_unknown_key' => '意外的密钥字段：:field。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'default_requires_active' => '只有已激活（已测试）的连接才能设为默认。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'delete_requires_default_handover' => '删除此连接前，请先将另一个连接设为默认。',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'test_not_configured' => '连接缺少端点、API 密钥或测试模型。',
        'test_unreachable' => '无法访问端点：:error',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'test_unauthorized' => '端点拒绝了 API 密钥（401/403）。',
        'test_http_failed' => '端点返回 HTTP :status。',
        'test_http_failed_with_body' => '端点返回 HTTP :status：:detail',
        // curated at 2026-09-22T12:19:24+07:00 — zh_CN curation wave
        'test_passed' => '连接测试通过。',
    ],
];
