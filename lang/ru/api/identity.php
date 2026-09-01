<?php

return [
    'oauth' => [
        'discord_not_configured' => 'Discord OAuth не настроен на этом сервере.',
        'token_exchange_failed' => 'Авторизация Discord не удалась. Повторите попытку.',
        'token_refresh_failed' => 'Сессия Discord истекла. Подключите Discord заново в разделе «Связанные аккаунты».',
        'connections_failed' => 'Не удалось загрузить связанные аккаунты Discord.',
        'discord_not_connected' => 'Сначала подключите Discord в разделе «Связанные аккаунты».',
        'discord_user_missing' => 'Discord не вернул идентификатор пользователя.',
        'authorize_url' => 'URL авторизации Discord выдан.',
        'spa_url_missing' => 'Origin SPA не настроен на этом сервере.',
        'return_path_invalid' => 'Недопустимый путь возврата OAuth.',
    ],
    'connections' => [
        'show' => 'Связанные аккаунты загружены.',
        'synced' => 'Связанные аккаунты обновлены.',
        'disconnected' => 'Аккаунт отключён.',
        'discord_disconnected' => 'Discord отключён.',
    ],
];
