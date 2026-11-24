<?php

return [
    'title' => 'Настройки',
    // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
    'subtitle' => 'Операционная конфигурация, управляемая через панель администратора.',
    // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
    'read_only' => 'У вас есть доступ к этим настройкам только для просмотра.',
    'source' => 'Текущий источник: :source',
    'groups' => [
        'platform' => 'Платформа',
        'notification_audiences' => 'Аудитории уведомлений',
        'currencies' => 'Валюты',
    ],
    'fields' => [
        // Platform — JWT
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.jwt.ttl' => 'Срок действия JWT-токена доступа (минут)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.jwt.refresh_ttl' => 'Окно обновления JWT (минут)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.jwt.remember_ttl' => 'Срок действия JWT «запомнить меня» (минут)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.jwt.refresh_skew' => 'Запас ти�
ого обновления JWT (минут)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.jwt.blacklist_grace_period' => 'Льготный период чёрного списка JWT (секунд)',
        // Platform — auth / 2FA
        'platform.auth.default_role' => 'Роль по умолчанию для новы�
 пользователей',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.auth.verification_expire' => 'Срок действия ссылки подтверждения электронной почты (минут)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.two_factor.window' => 'Окно проверки TOTP дву�
факторной аутентификации',
        'platform.two_factor.issuer' => 'Имя издателя 2FA (приложение-аутентификатор)',
        // Platform — hCaptcha
        'platform.hcaptcha.enabled' => 'hCaptcha включена',
        // Platform — notifications
        'platform.notifications.delivery' => 'Режим доставки уведомлений',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.notifications.polling_interval' => 'Интервал опроса уведомлений (секунд)',
        // Platform — error log
        'platform.errorlog.enabled' => 'Постоянный журнал ошибок включён',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.errorlog.clean_after_days' => 'Срок �
ранения журнала ошибок (дней)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.errorlog.max_message_length' => 'Максимальная длина сообщения журнала ошибок',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.errorlog.max_trace_length' => 'Максимальная длина трассировки журнала ошибок',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.errorlog.max_context_json_length' => 'Максимальная длина JSON-контекста журнала ошибок',
        // Platform — activity log
        'platform.activitylog.enabled' => 'Журнал активности включён',
        'platform.activitylog.buffer_enabled' => 'Буфер журнала активности включён',
        // Platform — localization
        'platform.localization.locale_cookie' => 'Имя cookie локали',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'identity.discord.connections_snapshot_max_age_minutes' => 'Максимальный возраст снимка подключений Discord (минут)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.localization.locale_cookie_minutes' => 'Срок действия cookie локали (минут)',
        // Platform — payment
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.payment.channel_fee_bearer' => 'Плательщик комиссии канала',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.payment.reporting_timezone' => 'Часовой пояс платёжной отчётности',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.payment.prefer_channel_checkout' => 'Предпочитать оформление заказа через канал',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.payment.default_buyer_phone' => 'Телефон покупателя по умолчанию (резерв iPaymu)',
        // Platform — mail identity
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.mail.from_address' => 'Адрес отправителя писем',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.mail.from_name' => 'Имя отправителя писем',
        // Platform — service
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.service.grace_days' => 'Льготные дни услуги',
        'platform.service.settlement_currency' => 'Валюта расчёта',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.service.purchase_max_quantity' => 'Максимальное количество для покупки услуги',
        // Platform — inference fallbacks
        'platform.inference.default_provider_slug' => 'Slug провайдера инференса по умолчанию',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.inference.default_input_per_million_usd' => 'Ставка ввода по умолчанию (USD за 1 млн токенов)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.inference.default_output_per_million_usd' => 'Ставка вывода по умолчанию (USD за 1 млн токенов)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.inference.unknown_input_per_million_usd' => 'Ставка ввода для неизвестной модели (USD за 1 млн токенов)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.inference.unknown_output_per_million_usd' => 'Ставка вывода для неизвестной модели (USD за 1 млн токенов)',
        // Platform — Frankfurter FX
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.frankfurter.cache_ttl' => 'Окно свежести курсов Frankfurter FX (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.frankfurter.timeout' => 'HTTP-таймаут Frankfurter FX (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
 загрузок',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
 частей multipart',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
ранилища по умолчанию (байт)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
 рабочи�
 областей',
ранилища',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
ранилища скачиваний включён',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
 бесплатны�
 скачиваний на пользователя',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
од в обычный режим после (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
од задач мышления в очередь отложенны�
 после (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
 запросов распознавания',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
 запросов мышления',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
    ],
    'hints' => [
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.mail.from_address' => 'Глобальный адрес отправителя для ис�
одящей почты (транспортные учётные данные остаются в .env).',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.payment.default_buyer_phone' => 'Резервный телефон, когда в профиле покупателя нет номера (требование iPaymu).',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.frankfurter.timeout' => 'HTTP-таймаут для запросов курсов Frankfurter FX.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:29:12+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:29:12+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.jwt.refresh_skew' => 'Обновлять токен доступа за столько минут до его истечения.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.notifications.delivery' => 'Режим реального времени использует WebSockets и требует настроенны�
 учётны�
 данны�
 PUSHER_* / broadcast; иначе колокольчик переключается на HTTP-опрос.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.payment.channel_fee_bearer' => 'Кто берёт на себя комиссию платёжного канала: продавец или покупатель.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.inference.default_provider_slug' => 'Резервный провайдер, если в запросе не указан другой.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.inference.default_input_per_million_usd' => 'Временная ставка оплаты для зарегистрированны�
 моделей без каталожны�
 цен.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.inference.unknown_input_per_million_usd' => 'Временная ставка оплаты для незарегистрированны�
 моделей.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
ода актива из ускоренной обработки в обычную.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        // curated at 2026-09-23T19:29:12+07:00 — ru curation wave: settled terms
ода стадии распознавания в льготный режим.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
ода стадии мышления в льготный режим.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
 задач.',
        // curated at 2026-09-27T19:30:00+07:00 — ru curation wave
        'identity.discord.connections_snapshot_max_age_minutes' => 'Как долго со�
ранённые снимки подключений Discord считаются достоверными, прежде чем прома�
 проверки вызовет живое обновление через Discord API.',
    ],
    'options' => [
        'platform' => [
            'notifications' => [
                'delivery' => [
                    'polling' => 'HTTP-опрос',
                    'realtime' => 'WebSocket (реальное время)',
                ],
            ],
        ],
    ],
];
