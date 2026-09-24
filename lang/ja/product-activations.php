<?php

declare(strict_types=1);

return [
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'list_success' => 'プロダクト有効化を読み込みました。',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'show_success' => 'プロダクト有効化を読み込みました。',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'admin_list_success' => 'プロダクト有効化を読み込みました。',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'stats_success' => 'プロダクト有効化の統計を読み込みました。',
    'suspended' => 'プロダクト有効化を一時停止しました。',
    'reactivated' => 'プロダクト有効化を再開しました。',
    'approved' => 'プロダクト有効化を承認しました。',
    'rejected' => 'プロダクト有効化を却下しました。',

    'not_found' => ':product のプロダクト有効化が見つかりません。',
    'unknown_product' => '不明なプロダクトキー: :product。',
    'already_active' => 'プロダクトは既に有効です。',
    'cannot_approve' => '状態 :state の有効化を承認できません。',
    'cannot_reject' => '状態 :state の有効化を却下できません。',
    'cannot_suspend' => '状態 :state の有効化を一時停止できません。',
    'cannot_reactivate' => '状態 :state の有効化を再開できません。',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'type_mismatch' => 'プロダクト :product は有効化タイプ :expected を想定していますが、実際は :actual です。',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'inactive' => 'プロダクト :product は :state のため、アクセスできません。',
    'not_provisioned' => 'プロダクト :product はまだプロビジョニングされていません。',
    'auth_required' => '認証が必要です。',

    'title' => 'プロダクト有効化',
    'subtitle' => 'ユーザーごとのプロダクト有効化レジストリ。',
    'col_user' => 'ユーザー',
    'col_product' => 'プロダクト',
    'col_type' => 'タイプ',
    'col_state' => '状態',
    'col_source' => 'ソース',
    'col_activated_at' => '有効化日時',
    'col_suspended_at' => '一時停止日時',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'col_expired_at' => '期限切れ日時',
    'col_updated_at' => '更新日時',
    // curated at 2026-09-24T09:43:18+07:00 — ja curation wave: multi-owner key — Actions column agrees with the other six namespaces (操作)
    'col_actions' => '操作',
    'filter_product' => 'プロダクト',
    'filter_state' => '状態',
    'filter_all' => 'すべて',
    'action_suspend' => '一時停止',
    'action_reactivate' => '再開',
    'action_approve' => '承認',
    'action_reject' => '却下',
    'reason_label' => '理由',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'reason_placeholder' => 'この操作の理由（任意）',
    'state_pending' => '保留中',
    'state_active' => '有効',
    'state_suspended' => '一時停止',
    'state_expired' => '期限切れ',
    'type_implicit' => '暗黙',
    'type_explicit' => '明示',

    // v2 — プロダクト別インサイト
    'products_title' => 'プロダクトドメイン',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'products_subtitle' => 'プロダクトドメインごとのインサイトと操作。',
    'products_empty' => '登録されたプロダクトドメインはありません。',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'products_list_success' => 'プロダクトの概要を読み込みました。',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'product_show_success' => 'プロダクト詳細を読み込みました。',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'user_detail_success' => 'ユーザーのプロダクト詳細を読み込みました。',
    'product_title' => 'プロダクト: :product',
    'view_product' => 'インサイト表示',
    'back_to_products' => 'プロダクト一覧へ戻る',
    'fulfillment_target' => 'フルフィルメントターゲット',
    'no_activations' => 'このプロダクトの有効化はありません。',
    'pagination' => '有効化のページネーション',

    'metric_total_storage_used_bytes' => '使用ストレージ合計',
    'metric_total_files' => 'ファイル合計',
    'metric_total_workspaces' => 'ワークスペース合計',
    'metric_paid_users_count' => '有料ユーザー',
    'metric_free_users_count' => '無料ユーザー',
    // curated at 2026-09-21T17:25:30+07:00 — ja curation wave
    'metric_grace_users_count' => '猶予期間のユーザー',
    'metric_storage_used_bytes' => '使用ストレージ',
    'metric_file_count' => 'ファイル',
    'metric_workspace_count_owned' => 'ワークスペース',

    // Tier labels
    'tier' => 'ティア',
    'tier_free' => '無料',
    'tier_paid' => '有料',
    'tier_grace' => '猶予期間',
    'tier_n/a' => 'N/A',
];
