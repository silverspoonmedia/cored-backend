<?php

declare(strict_types=1);

return [
    // Gate / middleware
    'auth_required' => '認証が必要です。',
    'unknown_product' => '不明なプロダクトドメイン: :product。',
    'forbidden' => 'このプロダクトドメインは非公開です。アクセスは Root、Admin、およびベータテスターに限定されています。',

    // Admin API
    // curated at 2026-09-21T17:25:29+07:00 — ja curation wave
    'list_success' => 'プロダクトドメインアクセス設定を読み込みました。',
    'updated' => 'プロダクトドメインの公開設定を更新しました。',
    'invalid_visibility' => '公開設定は public または private である必要があります。',

    // UI
    'title' => 'プロダクトドメインアクセス',
    'subtitle' => 'どのプロダクトドメインを公開またはベータテスター限定にするかを管理します。',
    'empty' => '登録されたプロダクトドメインはありません。',
    'col_domain' => 'プロダクトドメイン',
    'col_visibility' => '公開設定',
    'visibility_public' => '公開',
    'visibility_private' => '非公開',
    'public_hint' => '誰でもこのプロダクトドメインにアクセスできます。',
    'private_hint' => 'Root、Admin、およびベータテスターのみがアクセスできます。',
    'beta_role' => 'ベータロール: :role',
    'toggle_updated' => '公開設定を更新しました。',
    'toggle_failed' => '公開設定を更新できませんでした。',
];
