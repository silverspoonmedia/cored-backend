<?php

declare(strict_types=1);

return [
    // Gate / middleware
    'auth_required' => '需要身份验证。',
    'unknown_product' => '未知产品域：:product。',
    // curated at 2026-09-22T12:51:54+07:00 — zh_CN curation wave
    'forbidden' => '此产品域为私有域，仅 Root、Admin 及其内测人员可以访问。',

    // Admin API
    'list_success' => '产品域访问设置已获取。',
    'updated' => '产品域可见性已更新。',
    'invalid_visibility' => '可见性必须为 public 或 private。',

    // UI
    'title' => '产品域访问控制',
    // curated at 2026-09-22T12:51:54+07:00 — zh_CN curation wave
    'subtitle' => '控制哪些产品域公开，或限制为仅内测人员可访问。',
    'empty' => '尚未注册产品域。',
    'col_domain' => '产品域',
    'col_visibility' => '可见性',
    'visibility_public' => '公开',
    'visibility_private' => '私有',
    'public_hint' => '任何人都可以访问此产品域。',
    // curated at 2026-09-22T12:51:54+07:00 — zh_CN curation wave
    'private_hint' => '仅 Root、Admin 和内测人员可以访问。',
    // curated at 2026-09-22T12:51:54+07:00 — zh_CN curation wave
    'beta_role' => '内测角色：:role',
    'toggle_updated' => '可见性已更新。',
    'toggle_failed' => '无法更新可见性。',
];
