<?php

return [
    'connection' => [
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'list_success' => 'AI 接続を読み込みました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'show_success' => 'AI 接続を読み込みました。',
        'created' => 'AI 接続を作成しました。テストを実行して有効化してください。',
        'updated' => 'AI 接続を更新しました。再度テストを実行して有効化してください。',
        'deleted' => 'AI 接続を削除しました。',
        'default_updated' => 'デフォルトの AI 接続を更新しました。',
        'slug_required' => '接続スラッグは必須です。',
        'slug_taken' => 'その接続スラッグは既に使用されています。',
        'label_required' => '接続ラベルは必須です。',
        'endpoint_invalid' => 'エンドポイントは有効な http(s) URL である必要があります。',
        'test_model_required' => 'この接続を検査するにはテストモデルが必要です。',
        'payload_invalid' => '保存されたシークレットが無効です。API キーを再入力してください。',
        'payload_missing' => 'シークレットに必須フィールドがありません: :field',
        'payload_unknown_key' => '不明なシークレットフィールド: :field',
        'default_requires_active' => 'デフォルトにできるのは有効（テスト済み）な接続だけです。',
        'delete_requires_default_handover' => '削除する前に別の接続をデフォルトにしてください。',
        'test_not_configured' => 'エンドポイント、API キー、またはテストモデルが未設定です。',
        'test_unreachable' => 'エンドポイントに到達できません: :error',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'test_unauthorized' => 'エンドポイントが API キーを拒否しました（401/403）。',
        'test_http_failed' => 'エンドポイントが HTTP :status を返しました。',
        'test_http_failed_with_body' => 'エンドポイントが HTTP :status を返しました: :detail',
        'test_passed' => '接続テストに成功しました。',
    ],
];
