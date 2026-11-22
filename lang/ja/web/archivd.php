<?php

return [
    'title' => 'Archivd',
    // curated at 2026-11-22T19:30:00+07:00 — boilerplate seam wave (menu nodes)
    'menu' => [
        'my_files' => 'マイファイル',
        'trash' => 'ゴミ箱',
        'blocklist' => 'DMCA ブロックリスト',
    ],
    // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
    'blocklist_title' => 'ハッシュのブロックリスト（DMCA）',
    'blocklist_add' => 'ハッシュをブロック',
    'blocklist_sha256' => 'sha256',
    // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
    'blocklist_sha256_placeholder' => '64文字の16進数',
    'blocklist_reason' => '理由',
    // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
    'blocklist_reason_placeholder' => 'DMCA の参照番号など',
    'blocklist_entries' => 'ブロック済みハッシュ',
    'blocklist_added' => '追加日時',
    'blocklist_actions' => '操作',
    'blocklist_remove' => '削除',
    'blocklist_confirm_remove' => 'このハッシュをブロックリストから削除しますか？',
    'blocklist_empty' => 'ブロック済みハッシュはありません。',
    'list_title' => 'マイファイル',
    'show_title' => 'ファイル詳細',
    'trash' => 'ゴミ箱',
    'trash_title' => 'ゴミ箱',
    'trash_empty' => 'ゴミ箱は空です。',
    'back' => 'ファイルに戻る',
    'new_folder' => '新しいフォルダ',
    'folder_name' => 'フォルダ名',
    'file_name' => 'ファイル名',
    'create' => '作成',
    'cancel' => 'キャンセル',
    'download' => 'ダウンロード',
    'delete' => '削除',
    'view' => '表示',
    'rename' => '名前の変更',
    'save_rename' => '名前を保存',
    'save' => '保存',
    'share' => '共有',
    'share_link' => '共有リンク',
    'copy_link' => 'リンクをコピー',
    'created_at' => '作成日時',
    'empty' => 'このフォルダは空です。',
    'type_folder' => 'フォルダ',
    'type_file' => 'ファイル',
    'quota_used' => '使用容量',
    'confirm_delete_folder' => 'このフォルダと中身をゴミ箱へ移動しますか？',
    'confirm_delete_file' => 'このファイルをゴミ箱へ移動しますか？',
    'columns' => [
        'name' => '名前',
        'type' => '種類',
        'size' => 'サイズ',
        'region' => '地域',
        'visibility' => '公開設定',
        'actions' => '操作',
        'deleted_at' => '削除日時',
    ],
    'visibility' => [
        'label' => '公開設定',
        'private' => '制限付き',
        'anyone_with_link' => 'リンクを知っている全員',
        'hint_link' => 'このリンクを持つ人はファイルを表示・ダウンロードできます。「リンクを知っている全員」に保存後、再読み込みしてリンクをコピーしてください。',
        'hint_folder' => 'このリンクを持つ人はフォルダを閲覧し、中のファイルをダウンロードできます。保存後、再読み込みしてリンクをコピーしてください。',
    ],
    'stats' => [
        'views' => '閲覧数',
        'downloads' => 'ダウンロード数',
        'last_viewed' => '最終閲覧',
        'last_downloaded' => '最終ダウンロード',
    ],
    'public' => [
        'shared_file' => '共有ファイル',
        'shared_folder' => '共有フォルダ',
    ],
    'search' => '検索',
    'search_placeholder' => 'ファイルを検索…',
    'search_no_results' => 'ファイルが見つかりません。',
    'open' => '開く',
    'move' => '移動',
    'moved' => '移動しました。',
    'move_failed' => '移動に失敗しました。',
    'move_invalid' => 'ここには移動できません。',
    'move_title' => 'フォルダへ移動',
    'move_to' => '移動先',
    'select_folder' => '移動先フォルダを選択',
    'root_label' => 'マイファイル',
    'parent_folder' => '親フォルダ',
    'workspace_personal' => '個人',
    'workspace_team' => 'チームワークスペース',
    'workspace_activity' => 'チームアクティビティ',

    'microstock' => [
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'activate_cta' => 'Microstock を有効化',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'create_workspace' => '新しい Microstock ワークスペース',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'slots_full' => 'Microstock の枠が上限に達しました',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'create_title' => '新しい Microstock ワークスペース',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'create_hint' => 'Microstock ワークスペースに名前を付けます。空のままにすると既定の名前が使われます。',
        'name_label' => 'ワークスペース名',
        'name_placeholder' => 'Microstock',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'error_requires_folder' => '先にフォルダ（コレクション）を作成し、その中にファイルをアップロードしてください。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'error_collection_locked' => 'このコレクションはロックされているため、アップロードを受け付けません。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'error_image_only' => 'Microstock のコレクションでは画像ファイル（JPEG、PNG、WebP、GIF）のみアップロードできます。',
        'lock_describe' => 'ロックして説明',
        'analyze' => '分析を開始',
        'export_csv' => 'CSVをエクスポート',
        'download_csv' => 'CSVをダウンロード',
        // curated at 2026-09-27T20:30:00+07:00 - ja curation wave
        'upload_marketplace' => 'マーケットプレイスへアップロード',
        // curated at 2026-09-27T20:30:00+07:00 - ja curation wave
        'upload_no_credentials' => 'マーケットプレイスの認証情報がまだありません。先に追加してください。',
        // curated at 2026-09-27T20:30:00+07:00 - ja curation wave
        'upload_manage_credentials' => '認証情報を管理',
        // curated at 2026-09-27T20:30:00+07:00 - ja curation wave
        'credential_status_failed' => '失敗',
        // curated at 2026-09-27T20:30:00+07:00 - ja curation wave
        'upload_status_queued' => 'マーケットプレイスへのアップロードを受け付けました。',
        // curated at 2026-09-27T20:30:00+07:00 - ja curation wave
        'upload_status_uploading' => 'マーケットプレイスへアップロード中…',
        // curated at 2026-09-27T20:30:00+07:00 - ja curation wave
        'upload_status_uploaded' => 'マーケットプレイスへアップロードしました。',
        // curated at 2026-09-27T20:30:00+07:00 - ja curation wave
        'upload_status_failed' => 'マーケットプレイスへのアップロードに失敗しました。',
        'status_locked' => 'コレクションをロックしました。分析の準備ができました。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'status_processing' => 'AI がこのコレクションを分析しています…',
        'status_processing_degraded' => '（通常より時間がかかっています）',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'status_review' => '分析が完了しました。メタデータを確認してエクスポートしてください。',
        'status_exported' => 'コレクションをエクスポートしました。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'status_failed' => '分析に失敗しました。もう一度ロックして再試行してください。',
        'describe_title' => 'コレクションをロックして説明',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_hint' => 'このコレクション全体を説明してください。AI は各ファイルのタイトルとキーワードを生成する際のコンテキストとしてこの説明を使用します。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_sections_intro' => '詳細を入力するほど、すべてのファイルで一貫したタイトルとキーワードが生成されます。',
        'describe_label' => 'コレクションの説明',
        'describe_primary_concept' => '主なコンセプト',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_primary_placeholder' => 'このコレクションはどんな内容ですか？例: ストック購入者向けのエコフレンドリーなキッチンライフスタイル写真。',
        'describe_visible_facts' => '写っている被写体',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_buyer_intent' => '購入者の利用目的',
        'describe_style_mood' => 'スタイルと雰囲気',
        'describe_additional_notes' => '追加メモ（任意）',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_additional_placeholder' => '照明、小道具、制約（ロゴなし、文字なし）、繰り返しのテーマ…',
        'describe_other_placeholder' => 'その他（カンマ区切り）',
        'describe_preview' => '合成ヒントのプレビュー',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_char_count' => ':count / :max 文字',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_validation_primary' => '主なコンセプトを :min 文字以上で入力してください。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_validation_visible' => '被写体を 1 つ以上選択するか、「その他」に入力してください。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_validation_intent' => '購入者の利用目的を 1 つ以上選択するか、「その他」に入力してください。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_validation_style' => 'スタイルを 1 つ以上選択するか、「その他」に入力してください。',
        'describe_validation_too_long' => 'ヒントが長すぎます。メモや選択を短くしてください。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_warning_blind' => '説明を入れずにこのコレクションをロックしようとしています。AI はコンテキストなしでメタデータを生成するため、通常は品質が低く一貫性のない結果になります。続行しますか？',
        'describe_billing_mode_label' => '課金モード',
        'describe_billing_mode_hint' => 'このコレクションの分析方法を選択してください。',
        'describe_billing_mode_free' => '無料',
        'describe_billing_mode_paid' => '有料',
        'describe_validation_billing_mode' => '無料または有料の課金モードを選択してください。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'describe_placeholder' => '例: 木製テーブルの上に置いたエコフレンドリーなキッチン用品のフラットレイ写真集、明るい自然光。',
        'describe_submit' => 'ロックして分析',
        'describe_visible_options' => [
            'person' => '人物',
            // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
            'group' => '複数人',
            'object' => '物体',
            'icon' => 'アイコン',
            'animal' => '動物',
            'landscape' => '風景',
            'architecture' => '建築',
            'food' => '食べ物',
            'abstract' => '抽象',
            'typography' => 'タイポグラフィ',
        ],
        'describe_intent_options' => [
            'business' => 'ビジネス',
            'finance' => '金融',
            'education' => '教育',
            'learning' => '学習',
            'health' => '健康',
            'technology' => 'テクノロジー',
            'lifestyle' => 'ライフスタイル',
            'marketing' => 'マーケティング',
            'holidays' => 'ホリデー',
        ],
        'describe_style_options' => [
            'photo' => '写真',
            'flat_illustration' => 'フラットイラスト',
            // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
            'render_3d' => '3D レンダー',
            'vector' => 'ベクター',
            'cartoon' => 'カートゥーン',
            'minimal' => 'ミニマル',
            'vintage' => 'ヴィンテージ',
            'hand_drawn' => '手描き',
            'isometric' => 'アイソメトリック',
        ],
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'progress' => ':total 件中 :processed 件を分析済み',
        'progress_failed' => '（:failed 件失敗）',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'billing_line' => 'AI 残高 $:balance ・ 1 ファイルあたり約 $:estimate（このコレクションで約 $:total）',
        'daily_free_line' => '本日の無料分析: :remaining/:limit',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'billing_mixed_line' => '今回: 無料 :daily 件 + 有料 :paid 件',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'status_processing_free_tier' => '（無料枠のキュー — 時間がかかる場合があります）',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'insufficient_credits' => 'AI クレジットが不足しています。サービスページからクレジットパックを購入してください。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'stage_vision' => '画像を分析中…',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'stage_thinking' => '処理中…',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'stage_queued_thinking' => '処理待ち',
        'stage_degraded' => '時間がかかっています…',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'stage_vision_label' => '画像分析',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'stage_thinking_label' => '処理',
        'stage_result_label' => '結果',
        'stage_strip_label' => '分析ステージ',
        'review_files' => 'ファイルをレビュー',
        'review' => 'レビュー',
        'no_metadata' => 'メタデータなし',
        'status_pending' => '保留中',
        'status_generated' => '生成済み',
        'status_finalized' => '確定済み',
        'status_failed_item' => '分析に失敗',
        'panel_error_label' => '分析エラー',
        'panel_title' => 'アセットをレビュー',
        'panel_title_label' => 'タイトル',
        'panel_description_label' => '説明',
        'panel_category_label' => 'カテゴリ',
        'panel_keywords_label' => 'キーワード',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'panel_keyword_placeholder' => 'キーワードを入力して Enter',
        'panel_keyword_clear' => 'キーワードをすべて削除',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'panel_ai_details' => 'AI 詳細',
        'panel_save' => '保存',
        'panel_reanalyze' => '再分析',
        'panel_saved' => 'メタデータを保存しました。',
        'panel_queued' => '再分析をキューに追加しました。',
        'step_upload' => 'アップロード',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'step_lock_describe' => 'ロックして説明',
        'step_analyze' => '分析',
        'step_review' => 'レビュー',
        'step_export' => 'エクスポート',
        'step_upload_hint' => 'このコレクションフォルダに画像をアップロードして開始します。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'step_lock_hint' => 'コレクションを説明してロックすると、AI 分析が始まります。',
        'step_analyze_hint' => 'AIがファイルのタイトルとキーワードを生成しています。',
        'step_review_hint' => 'AI生成メタデータを確認・編集してエクスポートします。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'step_export_hint' => 'コレクションをエクスポートしました。マーケットプレイス用の CSV をダウンロードしてください。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'step_upload_empty' => 'このコレクションは空です。画像をアップロードして始めてください。',
    ],
    'collab' => [
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'title' => 'コラボレーション',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'subtitle' => 'チームストレージはオーナーの有料プランの枠を使用します。別メニューではなく、ここでモードを切り替えます。',
        'badge_free' => '無料プラン',
        'badge_paid' => '有料プラン',
        'badge_grace' => '猶予期間',
        'free_heading' => '無料プランでできること:',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'free_can_personal' => '個人ストレージ（無料枠 5 GB）を使用する。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'free_can_link' => '「リンクを知っている全員」で個別のファイルやフォルダを共有する。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'free_can_join' => '招待されれば他の人のチームに参加する（容量はオーナー負担）。',
        'free_cannot_heading' => '無料プランでできないこと:',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'free_cannot_create' => 'チームワークスペースを作成する。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'free_cannot_invite' => '自分がオーナーのチームにメンバーを招待する（有料のメインプランまたはアドオンプランが必要）。',
        'upgrade_cta' => 'プランを見る / アップグレード',
        'grace_heading' => 'プランは猶予期間中',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'grace_body' => 'プランを更新するまで、アップロードとコラボ機能はブロックされます。既存の個人ファイルは引き続き閲覧できます。',
        'renew_cta' => 'プランを更新',
        'paid_heading' => '有料特典が有効:',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'paid_can_create' => 'チームワークスペースを最大 10 個作成（Team Workspace Slot で追加可）し、メンバーを招待できます。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'paid_can_invite' => 'チームのファイルはオーナーの容量枠に計上されます。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'paid_can_join' => '自分のチームを持ちながら他の人のチームにも参加できます（ロールはワークスペースごと）。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'paid_quota_note' => 'アクティビティログに、チーム項目の追加・改名・移動・削除を行ったユーザーが記録されます。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'create_workspace' => 'チームワークスペースを作成',
        'open_my_workspace' => '自分のチームを開く: :name',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'switch_label' => 'ワークスペースを切り替え:',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'no_teams_free' => 'チームはまだありません。アップグレードして作成するか、下の招待を承認してください。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'no_teams_paid' => 'チームはまだありません。上で作成するか、他の人のチームへの招待を承認してください。',
        'accept_invite' => '招待トークンがある',
        'invite_token' => '招待トークン',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'invite_token_placeholder' => 'オーナーから受け取ったトークンを貼り付け',
        'accept' => 'チームに参加',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'accept_hint' => '無料/有料どちらでも利用できます。自分のチームを所有していても他のチームに参加できます。容量は参加先のオーナー負担です。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'invite_token_ready' => '招待を作成しました — このトークンをメンバーに共有してください:',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'invite_token_share' => '現在メール送信は未対応です。トークンをコピーしてご自身で送信してください。',
        'team_context' => 'チーム: :name',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'you_are_role' => 'あなたのロール: :role',
        'badge_yours' => '自分の',
        'badge_joined' => '参加中',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'role_owner_short' => 'オーナー',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'role_admin_short' => '管理者',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'role_editor_short' => '編集者',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'role_viewer_short' => '閲覧者',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'team_quota_note' => 'ここではオーナーの容量枠を使用します。メンバー個人の無料枠ではありません。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'confirm_archive' => 'このワークスペースをアーカイブしますか？メンバーはアクセスできなくなります。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'archive' => 'ワークスペースをアーカイブ',
        'col_member' => 'メンバー',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'col_role' => 'ロール',
        'remove' => '削除',
        'confirm_remove' => 'このメンバーを削除しますか？',
        'invite_email' => 'メールで招待',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'invite_role' => 'ロール',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'role_editor' => '編集者（アップロードと編集）',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'role_viewer' => '閲覧者（読み取りのみ）',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'role_admin' => '管理者（メンバー管理）',
        'send_invite' => '招待を送信',
        'invite_result_hint' => '送信後、上にトークンが表示されます。共有して参加してもらってください。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'create_hint' => '有料プランには所有チームが 10 個含まれます。追加は Team Workspace Slot（1 つあたり月額 $1）で購入できます。他のチームへの参加は無制限です。',
        'workspace_name' => 'ワークスペース名',
        'default_workspace_name' => 'チームワークスペース',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'member_readonly_hint' => 'ここではメンバー権限です。招待や削除はオーナーまたは管理者のみ行えます。',
        'team_settings' => 'チーム',
        'slots_counter' => '所有チーム: :used / :limit',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'slots_full' => '所有チームの上限に達しました',
        'buy_slot_cta' => 'チーム枠を追加',
        'grace_body_short' => 'チーム作成・管理にはプラン更新が必要です。',
        'no_members' => 'メンバーはまだいません。',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'activity_empty' => 'チームのアクティビティはまだありません。',
    ],
    'restore' => '復元',
    'force_delete' => '完全に削除',
    'confirm_restore' => 'このアイテムを復元しますか？',
    'confirm_force_delete' => 'このアイテムを完全に削除しますか？元に戻せません。',
    'view_grid' => 'グリッド表示',
    'view_list' => 'リスト表示',
    'view_toggle' => '表示モード',
    'drag_drop_hint' => 'またはクリックして選択',
    'upload_here' => 'ファイルをここにドロップしてアップロード',
    'quota_remaining' => '残り',
    'quota_unlimited' => '無制限',
    'load_failed' => '読み込みに失敗しました。',
    // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
    'vue_mount_failed' => 'Archivd UI を読み込めませんでした。ページを再読み込みしてください。',
    'link_copied' => 'リンクをクリップボードにコピーしました。',
    'ajax_success' => '操作が完了しました。',
    'ajax_error' => '操作に失敗しました。',
    // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
    'network_error' => 'ネットワークエラーが発生しました。もう一度お試しください。',
    'type_image' => '画像',
    // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
    'type_pdf' => 'PDF 文書',
    'type_video' => '動画',
    'type_audio' => 'オーディオ',
    'type_archive' => 'アーカイブ',
    'type_document' => 'ドキュメント',
    'type_spreadsheet' => 'スプレッドシート',
    'type_code' => 'コードファイル',
    'upload' => [
        'start' => 'アップロード開始',
        'browse' => 'ファイルを選択',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'region' => 'アップロード先リージョン',
        'too_many' => '選択したファイルが多すぎます。',
        'wait' => 'アップロードの完了を待ってください。',
        'failed' => 'アップロードに失敗しました。',
        'pending' => '待機中',
        'waiting' => '順番待ち…',
        'uploading' => 'アップロード中…',
        // curated at 2026-09-21T17:29:05+07:00 — ja curation wave
        'completing' => '完了処理中…',
        'done' => '完了',
        'remove' => '削除',
    ],

    'regions' => [
        'ce' => '中央ヨーロッパ',
        'us' => 'アメリカ合衆国',
    ],

    'bulk' => [
        'selected' => '選択中',
        'select_all' => 'すべて選択',
        'select_item' => 'アイテムを選択',
        'move' => '移動',
        'share' => '共有',
        'unshare' => '非公開にする',
        'delete' => '削除',
        'confirm_share' => '選択した {n} 件をリンクを知る全員に共有しますか？',
        'confirm_unshare' => '選択した {n} 件を非公開にしますか？',
        'confirm_delete' => '選択した {n} 件をゴミ箱に移動しますか？',
    ],
];
