<?php

return [
    'oauth' => [
        'discord_not_configured' => 'このサーバーではDiscord OAuthが設定されていません。',
        'token_exchange_failed' => 'Discordの認可に失敗しました。もう一度お試しください。',
        'token_refresh_failed' => 'Discordセッションの有効期限が切れました。連携アカウントからDiscordを再接続してください。',
        'connections_failed' => 'Discordのリンクアカウントを読み込めませんでした。',
        'discord_not_connected' => '先に連携アカウントからDiscordを連携してください。',
        'discord_user_missing' => 'DiscordからユーザーIDが返されませんでした。',
        'authorize_url' => 'Discord認可URLを発行しました。',
        'spa_url_missing' => 'このサーバーではSPAのオリジンが設定されていません。',
        'return_path_invalid' => 'OAuthの戻り先パスは許可されていません。',
    ],
    'connections' => [
        'show' => '連携アカウントを読み込みました。',
        'synced' => 'リンクアカウントを更新しました。',
        'disconnected' => 'アカウントの連携を解除しました。',
        'discord_disconnected' => 'Discord連携を解除しました。',
    ],
];
