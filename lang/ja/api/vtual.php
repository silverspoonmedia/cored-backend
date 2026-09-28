<?php

return [
    'creator' => [
        'list_success' => 'クリエイター一覧を読み込みました。',
        'owned_list_success' => '所有している vTuber プロフィールを読み込みました。',
        'show_success' => 'クリエイターのプロフィールを読み込みました。',
        'created' => 'クリエイターを作成しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'orphan_created' => 'オーナーなしのクリエイターを作成しました（申請可能）。',
        'updated' => 'クリエイターを更新しました。',
        'archived' => 'クリエイターをアーカイブしました。',
        'name_required' => '表示名は必須です。',
        'slug_taken' => 'このクリエイタースラッグは既に使用されています。',
        'not_found' => 'クリエイタープロフィールが見つかりません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'self_create_disabled' => 'プロフィールの直接作成は無効です。既存のプロフィールを申請するか、スタッフに作成を依頼してください。',
    ],
    'orphan' => [
        'list_success' => 'オーファンクリエイター一覧を読み込みました。',
        'show_success' => 'オーファンクリエイターを読み込みました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'not_claimable' => 'このプロフィールはオーファンではなくなりました（申請済みまたは非アクティブ）。',
    ],
    'vtuber' => [
        'show_success' => 'vTuber プロフィールを読み込みました。',
        'not_found' => 'vTuber プロフィールが見つからないか、利用できません。',
    ],
    'public' => [
        'directory_success' => '公開 vTuber ディレクトリを読み込みました。',
        'show_success' => '公開 vTuber プロフィールを読み込みました。',
        'contents_success' => '公開コンテンツフィードを読み込みました。',
        'landing_success' => '公開ランディングペイロードを読み込みました。',
        'management_show_success' => '公開マネジメントプロフィールを読み込みました。',
    ],
    'merge' => [
        'success' => 'プロフィールをマージしました。ソースはアーカイブされ、データは宛先に移りました。',
        'self' => '同じプロフィール同士はマージできません。',
        'owner_required' => 'マージにはソースと宛先の両方でオーナーである必要があります。',
        'destination_archived' => '宛先プロフィールはアーカイブ済みでマージを受け取れません。',
        'source_archived' => 'ソースプロフィールは既にアーカイブ済みです。',
        'channel_clash' => '両プロフィールに同じプラットフォームチャンネル (:external_id) があります。先に一方をアーカイブ/削除してください。',
    ],
    'claim' => [
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'claimable_success' => '申請可能なクリエイターを読み込みました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'list_success' => 'あなたの申請を読み込みました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'show_success' => '申請を読み込みました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'started' => '申請を開始しました。トークンをチャンネルの概要欄（About/bio）に入れてから検証してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'verified' => '申請が承認されました。あなたがオーナーです。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'verify_queued' => '検証をキューに追加しました。バッチの準備が整い次第実行されます（保留が最大 50 件に達するか、待機時間の経過後）。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'verify_already_queued' => 'この申請の検証は既にキューに入っています。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'blocked' => '現在このプロフィールは申請できません。ブロック期間の終了後に再試行してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'cancelled' => '申請を取り消しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'accepted_anyway' => 'bio の確認なしで申請を承認しました（スタッフ）。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'not_claimable' => 'このクリエイターは申請できません（オーナーがいるか、非アクティブです）。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'channel_mismatch' => '証明チャンネルは申請対象のクリエイターに属している必要があります。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'token_not_found' => 'チャンネルの説明欄（bio）に申請トークンが見つかりません。トークンを追加して再試行してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'not_pending' => 'この申請は保留中ではありません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'already_owned' => 'このクリエイターには既にオーナーがいます。申請を完了できません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'expired' => 'この申請は期限切れです。新しい申請を開始してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'max_attempts' => '検証の試行回数が上限に達しました。新しい申請を開始するか、スタッフに連絡してください。',
        'cooldown' => ':seconds 秒待ってから再度検証してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'accept_anyway_disabled' => 'この環境では「確認なしで承認」は無効です。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'discord_verify_disabled' => 'この環境では Discord による申請の検証は無効です。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'discord_not_connected' => 'Discord による検証を使う前に、連携アカウントから Discord を連携してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'discord_connections_failed' => 'Discord のリンクアカウントを読み込めませんでした。Discord の再接続をお試しください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'discord_no_match' => 'Discord プロフィールに、一致する認証済みの YouTube または Twitch アカウントが見つかりませんでした。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'discord_verify_unsupported_platform' => 'このプラットフォームでは Discord による検証は利用できません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'channel_missing' => '申請に使う証明チャンネルが見つかりません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'max_pending' => '保留中の申請が既に :max 件あります。1 件取り消すか、検証を完了してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'pending_success' => '保留中の申請を読み込みました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'superseded' => 'このクリエイターの別の申請が既に完了したため、この申請は終了しました。',
    ],
    'management' => [
        'list_success' => 'マネジメント一覧を読み込みました。',
        'show_success' => 'マネジメントを読み込みました。',
        'created' => 'マネジメントを作成しました。',
        'updated' => 'マネジメントを更新しました。',
        'deleted' => 'マネジメントをアーカイブしました。',
        'name_required' => 'マネジメント名は必須です。',
        'slug_taken' => 'このマネジメントスラッグは既に使用されています。',
        'creator_assigned' => 'クリエイターをマネジメントに割り当てました。',
        'creator_unassigned' => 'クリエイターの割り当てを解除しました。',
        'assignment_not_found' => '有効なマネジメント割り当てが見つかりません。',
        'not_found' => 'マネジメントプロフィールが見つからないか、利用できません。',
        'members_list_success' => 'マネジメントメンバーを読み込みました。',
        'member_added' => 'メンバーをマネジメントに追加しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'member_updated' => 'メンバーのロールを更新しました。',
        'member_already_exists' => 'このユーザーは既にマネジメントのメンバーです。',
        'member_not_found' => 'マネジメントメンバーが見つかりません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'role_invalid' => 'メンバーのロールまたはユーザーが無効です。',
        'cannot_edit_self' => '自分のメンバーシップは変更・削除できません。',
        'last_owner' => 'マネジメントには少なくとも1人のオーナーが必要です。',
        'invites_list_success' => 'マネジメント招待を読み込みました。',
        'invite_sent' => '招待を送信しました。',
        'invite_cancelled' => '招待をキャンセルしました。',
        'invite_accepted' => '招待を承認しました。マネジメントへようこそ。',
        'invite_declined' => '招待を辞退しました。',
        'invite_already_pending' => 'このメールアドレスへの保留中の招待が既にあります。',
        'invite_not_actionable' => 'この招待はもう変更できません。',
        'invite_expired' => 'この招待は期限切れまたは無効です。',
        'invite_wrong_account' => 'この招待は別のアカウント宛てです。',
        'invite_user_not_found' => 'このメールアドレスの登録ユーザーが見つかりません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'owner_invite_not_claimable' => 'このマネジメントには既にオーナーがいます。オーナー招待は、まだオーナーがいない間のみ利用できます。',
        'creator_invite_mode_invalid' => 'クリエイター招待モードは "existing" または "new" を指定してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'creator_invite_not_owner' => '招待されたアカウントは、選択された vTuber プロフィールのオーナーではありません。',
        'creator_already_linked' => 'その vTuber プロフィールは既にこのマネジメントに紐付いています。',
        'creator_invite_name_required' => '新しい vTuber プロフィールの表示名は必須です。',
        'events_list_success' => 'マネジメントイベントを読み込みました。',
        'event_created' => 'イベントを作成しました。',
        // curated at 2026-09-24T18:10:00+07:00 — ja curation wave: vTual management event edit
        'event_updated' => 'イベントを更新しました。',
        'event_cancelled' => 'イベントを中止しました。',
        'event_not_found' => 'このマネジメントのイベントが見つかりません。',
        'event_not_actionable' => 'このイベントはこれ以上変更できません。',
        // curated at 2026-09-27T19:30:00+07:00 — ja curation wave: クリエイター単位のアクセス権限付与
        'grants_list_success' => 'クリエイターへのアクセス権限を読み込みました。',
        'grant_created' => 'クリエイターへのアクセス権限を付与しました。',
        'grant_revoked' => 'クリエイターへのアクセス権限を取り消しました。',
        'grant_not_found' => 'クリエイターへのアクセス権限が見つかりません。',
        'grant_capability_invalid' => '付与するアクセス権限のレベルが正しくありません。',
        'grant_creator_not_assigned' => 'アクセス権限を付与するには、先にこのマネジメントにクリエイターを紐付けてください。',
    ],
    'mail' => [
        'invite_subject' => ':management への招待',
        'creator_invite_subject' => ':management からの vTuber プロフィール招待',
        'creator_invite_line' => 'vTuber プロフィールを :management に紐付けるよう招待されました。',
        'invite_greeting' => 'こんにちは！',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'invite_line' => ':management に :role として参加するよう招待されました。',
        'invite_action' => '招待を確認する',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'invite_expiry' => 'この招待は :date に有効期限が切れます。',
    ],
    'channel' => [
        'list_success' => 'チャンネル一覧を読み込みました。',
        'show_success' => 'チャンネルの詳細を読み込みました。',
        'created' => 'チャンネルを登録し、検証しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'seeded' => 'チャンネルを解決して登録しました（スタッフによる投入で、bio での申請は不要）。',
        'registered_pending' => 'チャンネルをバッチ解決キューに追加しました（即時 API 呼び出しなし）。',
        'refreshed' => 'チャンネルプロフィールをプラットフォームから更新・検証しました。',
        'refresh_queued' => 'チャンネル更新をキューに追加しました（他の保留中リクエストとまとめて処理）。',
        'archived' => 'チャンネルをアーカイブしました。',
        'deleted' => '保留チャンネルを削除しました。',
        'pending_not_archivable' => '保留チャンネルはアーカイブできません。削除してください。',
        'not_pending_delete' => 'この操作で削除できるのは保留チャンネルのみです。',
        'restored' => 'チャンネルをアクティブに復元しました。',
        'promoted' => 'チャンネルを公開に昇格しました。プロフィール取得とコンテンツバックフィルをキューに追加しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'not_private_discovered' => '昇格できるのは、非公開で検出されたチャンネルのみです。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'promote_not_discovered' => 'この操作で昇格できるのは、Discord で検出されたチャンネルのみです。',
        'promote_unsupported_platform' => 'このプラットフォームでは公開への昇格に対応していません。',
        'not_archived' => 'チャンネルはアーカイブ状態ではありません。',
        'lifecycle_cooldown' => 'このチャンネルを再度アーカイブまたは復元するまで :seconds 秒お待ちください。',
        'lifecycle_rate_limited' => 'チャンネルのアーカイブ/復元操作が多すぎます。しばらくしてから再試行してください。',
        'archived_list_success' => 'アーカイブ済みチャンネルを読み込みました。',
        'platform_unknown' => '不明なプラットフォームです。',
        'platform_disabled' => 'このプラットフォームはまだ有効ではありません。',
        'external_id_required' => '外部チャンネル ID は必須です。',
        'external_id_taken' => 'このプラットフォーム ID のチャンネルは既に存在します。',
        'duplicate_on_creator' => 'このプラットフォームチャンネルは既にこの vTuber プロフィールに登録されています。',
        'profile_required' => 'チャンネルプロフィールは必須です（ID、ハンドル、ログイン、または URL）。',
        'profile_invalid' => 'チャンネルプロフィールを解析できません。プラットフォーム ID、@ハンドル/ログイン、またはプロフィール URL を指定してください。',
        'profile_platform_mismatch' => 'このプロフィール URL は別のプラットフォームのものです。プラットフォームを切り替えるか、一致するプロフィールを入力してください。',
        'profile_not_found' => 'このプロフィールに一致するチャンネルがプラットフォーム上にありません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'profile_unsupported_platform' => 'このプラットフォームではプロフィールの解決に対応していません。',
    ],
    'channel_claim' => [
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'list_success' => 'チャンネル申請を読み込みました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'show_success' => 'チャンネル申請を読み込みました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'started' => 'チャンネル申請を開始しました。トークンをチャンネルの概要欄（About/bio）に入れてから検証してください。',
        'verified' => 'チャンネルを検証し、このプロフィールに紐付けました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'cancelled' => 'チャンネル申請を取り消しました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'accepted_anyway' => 'bio の確認なしでチャンネルを承認しました（スタッフ）。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'token_not_found' => 'チャンネルの説明欄（bio）に申請トークンが見つかりません。トークンを追加して再試行してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'not_pending' => 'このチャンネル申請は保留中ではありません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'expired' => 'このチャンネル申請は期限切れです。新しい申請を開始してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'max_attempts' => '検証の試行回数が上限に達しました。新しい申請を開始するか、スタッフに連絡してください。',
        'cooldown' => '再検証まで :seconds 秒待ってください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'accept_anyway_disabled' => 'この環境では「確認なしで承認」は無効です。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'pending_other' => '別のユーザーが既にこのプラットフォームチャンネルの保留中の申請を持っています。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'discord_verify_disabled' => 'チャンネル申請の Discord 検証は無効です。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'discord_verify_unsupported_platform' => 'このプラットフォームでは Discord 検証はサポートされていません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'discord_not_connected' => '検証する前に、プロフィール → 連携アカウントで Discord を連携してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'discord_connections_failed' => 'Discord の接続を読み込めませんでした。再試行するか、bio トークンによる検証を使用してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'discord_no_match' => 'このプラットフォームチャンネルに一致する検証済みの Discord 接続がありません。',
        'discord_verified' => 'Discordで検証され、このプロフィールにチャンネルが紐づけられました。',
    ],
    'link' => [
        'list_success' => '外部リンクを読み込みました。',
        'created' => '外部リンクを追加しました。',
        'updated' => '外部リンクを更新しました。',
        'deleted' => '外部リンクを削除しました。',
        'url_required' => 'リンク URL は必須です。',
        'not_creator' => 'このリンクは vTuber プロフィールのものではありません。',
    ],
    'content' => [
        'external_id_required' => 'プラットフォームのコンテンツ ID は必須です。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'appearance_primary_via_set' => 'プライマリホストチャンネルは出演チャンネルとして追加できません。setPrimary を使用してください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'appearance_role_invalid' => 'コンテンツの出演ロールが無効です。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'appearance_platform_mismatch' => '出演チャンネルはコンテンツと同じプラットフォームである必要があります。',
        'appearance_already_primary' => 'このチャンネルは既にコンテンツのプライマリホストです。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'appearance_already_attached' => 'このチャンネルは既にコンテンツの出演チャンネルとして登録されています。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'appearance_not_found' => 'このチャンネルのコンテンツ出演情報が見つかりません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'cannot_detach_primary' => 'プライマリホストチャンネルは取り外せません。先に別のプライマリを設定してください。',
        'fetch_platform_unsupported' => 'プラットフォーム :platform ではコンテンツ取得はサポートされていません。',
        'fetch_channel_not_active' => 'コンテンツ取得には有効なチャンネルが必要です。',
        'fetch_video_channel_mismatch' => '動画 :video はチャンネル :channel に属していません。',
    ],
    'platform' => [
        'list_success' => 'プラットフォーム一覧を読み込みました。',
    ],
    'credential' => [
        'list_success' => 'プラットフォーム認証情報を読み込みました。',
        'show_success' => 'プラットフォーム認証情報を読み込みました。',
        'created' => 'プラットフォーム認証情報を作成しました。',
        'updated' => 'プラットフォーム認証情報を更新しました。',
        'deleted' => 'プラットフォーム認証情報を削除しました。',
        'revoked' => 'プラットフォーム認証情報を失効させました。',
        'label_required' => '認証情報ラベルは必須です。',
        'label_taken' => 'この認証情報ラベルは既にこのプラットフォームで使用されています。',
        'payload_invalid' => '認証情報のシークレットペイロードが無効です。',
        'payload_missing' => 'この認証情報タイプではシークレット項目 :field が必須です。',
        'payload_unknown_key' => 'シークレット項目 :field はこの認証情報タイプでは許可されていません。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'test_success' => '認証情報のテストに成功しました。認証情報が有効になりました。',
        'test_missing_secret' => 'このテストに必要なシークレットが不足しています。',
        'test_unsupported' => 'このプラットフォームと認証情報タイプの組み合わせに対応するプローブがありません。',
        'test_not_allowed_status' => '失効した認証情報はテストできません。',
        'test_youtube_ok' => 'YouTube API キーを受け付けました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'test_youtube_empty' => 'プローブ用のチャンネル ID に対して YouTube API がチャンネル情報を返しませんでした。',
        'test_twitch_ok' => 'Twitch アプリトークンを発行し、Helix プローブに成功しました。',
        'test_bearer_ok' => 'Bearer トークンがプラットフォームプローブに受け入れられました。',
        'test_bearer_ok_no_probe' => 'Bearer トークンはあります。このプラットフォームではライブプローブをスキップしました。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'status_active_requires_test' => '認証情報を有効にするには「テスト」を実行してください。',
        'revoke_requires_active' => '失効できるのは有効な認証情報のみです。',
        'delete_requires_revoke' => '削除する前にこの認証情報を失効させてください。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'not_active' => '認証情報が有効になっていません。',
        'app_token_oauth_only' => 'アプリアクセストークンは oauth_app 認証情報のみ対象です。',
        'app_token_twitch_only' => 'アプリアクセストークンの発行は Twitch のみ実装されています。',
    ],
    // curated at 2026-09-27T19:20:00+07:00 — vTual incidents wave
    'incident' => [
        'list_success' => '連携インシデントを読み込みました。',
        'show_success' => '連携インシデントの詳細を読み込みました。',
        'status_updated' => 'インシデントのステータスを更新しました。',
        'kinds' => [
            'websub' => 'YouTube WebSub',
            'eventsub' => 'Twitch EventSub',
            'eventsub_notification' => 'Twitch EventSub 配信',
            'credential' => 'プラットフォーム認証情報',
            'platform_http' => 'プラットフォーム API',
            'ingestion' => 'コンテンツ取り込み',
        ],
        'severities' => [
            'info' => '情報',
            'warning' => '警告',
            'critical' => '重大',
        ],
        'statuses' => [
            'open' => '未対応',
            'acknowledged' => '確認済み',
            'resolved' => '解決済み',
        ],
    ],
    'policy' => [
        'denied' => 'この操作を行う権限がありません。',
        'access_denied' => 'この vTual リソースにアクセスできません。',
        'mutate_denied' => 'この vTual リソースを変更できません。',
        'archive_denied' => 'このマネジメントをアーカイブできるのはオーナーまたは管理者メンバーのみです。',
        'members_denied' => 'このリソースのメンバーを管理できません。',
        'assign_denied' => 'このマネジメントにクリエイターを割り当てできません。',
    ],
    'integrations' => [
        'unsupported_operation' => 'プラットフォーム :platform は操作 :operation をサポートしていません。',
        'platform_disabled' => 'プラットフォーム :platform は無効または利用できません。',
        'platform_unknown' => '不明なプラットフォーム :platform です。',
        // curated at 2026-09-21T17:32:30+07:00 — ja curation wave
        'no_active_credential' => 'プラットフォーム :platform に有効な認証情報がありません。作成してテストしてください。',
        'credential_required' => 'プラットフォームクライアントに認証情報がバインドされていません。',
        'missing_api_key' => 'YouTube 認証情報に api_key がありません。',
        'missing_client_id' => 'Twitch 認証情報に client_id がありません。',
        'ids_required' => ':label を少なくとも 1 つ指定してください。',
        'batch_too_large' => ':label が多すぎます（:count）。1 リクエストの上限は :max です。',
        'batch_max_invalid' => 'バッチ上限の設定が無効です。',
        'invalid_part' => 'YouTube の :resource part ":part" は許可されていません。',
        'parts_required' => 'YouTube の :resource part を少なくとも 1 つ指定してください。',
        'handle_required' => 'チャンネルのハンドルまたはログインが必要です。',
        'youtube_channel_id_required' => '有効な YouTube チャンネル ID（UC…）が必要です。',
        'search_event_type_invalid' => '検索イベントタイプは live、upcoming、completed のいずれかである必要があります。',
        'feed_fetch_failed' => 'YouTube フィードのリクエストに失敗しました（HTTP :status）。',
        'feed_parse_failed' => 'YouTube フィードの応答を解析できませんでした。',
        'upstream_error' => 'プラットフォーム :platform へのリクエストが失敗しました（HTTP :status）。',
        'upstream_error_detail' => 'プラットフォーム :platform へのリクエストが失敗しました（HTTP :status）: :detail',
        'upstream_connection_error' => 'プラットフォーム :platform への接続に失敗しました。',
        // curated at 2026-09-25T21:30:00+07:00 — upstream retry/quota ops log lines
        'upstream_retry_scheduled' => 'プラットフォーム :platform へのリクエストが失敗しました（HTTP :status）。:delay ミリ秒後に :attempt 回目の再試行を行います。',
        'upstream_quota_exhausted' => 'プラットフォーム :platform のクォータを使い切りました（HTTP 429）。クォータがリセットされてから再試行してください。',
        'probe_success' => 'プラットフォーム API プローブが完了しました。',
        'probe_failed' => 'プラットフォーム API プローブが予期せず失敗しました。',
        'probe_ids_required' => 'このメソッドには少なくとも 1 つの id が必要です。',
        'probe_unknown_method' => '不明なプローブメソッド :method です。',
    ],
];
