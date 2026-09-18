<?php

return [
    'connection' => [
        'list_success' => 'AI 连接获取成功。',
        'show_success' => 'AI 连接获取成功。',
        'created' => 'AI 连接已创建。请运行测试以启用。',
        'updated' => 'AI 连接已更新。请再次运行测试以启用。',
        'deleted' => 'AI 连接已删除。',
        'default_updated' => '默认 AI 连接已更新。',
        'slug_required' => '连接 slug 为必填项。',
        'slug_taken' => '该连接 slug 已被使用。',
        'label_required' => '连接名称为必填项。',
        'endpoint_invalid' => '端点必须是有效的 http(s) URL。',
        'test_model_required' => '需要测试模型才能探测此连接。',
        'payload_invalid' => '已保存的密钥无效。请重新输入 API key。',
        'payload_missing' => '密钥缺少必填字段：:field',
        'payload_unknown_key' => '未知的密钥字段：:field',
        'default_requires_active' => '只有已激活（已测试）的连接才能成为默认。',
        'delete_requires_default_handover' => '删除前请先将另一个连接设为默认。',
        'test_not_configured' => '连接缺少端点、API key 或测试模型。',
        'test_unreachable' => '无法访问端点：:error',
        'test_unauthorized' => '端点拒绝了 API key（401/403）。',
        'test_http_failed' => '端点返回 HTTP :status。',
        'test_http_failed_with_body' => '端点返回 HTTP :status：:detail',
        'test_passed' => '连接测试通过。',
    ],
];
