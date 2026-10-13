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
        'archivd' => 'Archivd',
        'microstock' => 'Microstock',
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
        'platform.jwt.refresh_skew' => 'Запас тихого обновления JWT (минут)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.jwt.blacklist_grace_period' => 'Льготный период чёрного списка JWT (секунд)',
        // Platform — auth / 2FA
        'platform.auth.default_role' => 'Роль по умолчанию для новых пользователей',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.auth.verification_expire' => 'Срок действия ссылки подтверждения электронной почты (минут)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.two_factor.window' => 'Окно проверки TOTP двухфакторной аутентификации',
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
        'platform.errorlog.clean_after_days' => 'Срок хранения журнала ошибок (дней)',
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
        // Archivd
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.max_file_size_bytes' => 'Максимальный размер файла (байт)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.max_files_per_batch' => 'Максимум файлов в пакете',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.max_concurrent_uploads' => 'Максимум одновременных загрузок',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.max_concurrent_parts' => 'Максимум одновременных частей multipart',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.chunk_size_bytes' => 'Размер фрагмента загрузки (байт)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.multipart_threshold_bytes' => 'Порог multipart-загрузки (байт)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.max_presigned_parts_per_response' => 'Максимум presigned-частей в ответе',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.presign_ttl_minutes' => 'Срок действия presigned URL (минут)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.session_ttl_hours' => 'Срок действия сессии загрузки (часов)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.stale_upload_grace_hours' => 'Льготный период устаревшей загрузки (часов)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.orphan_inactivity_minutes' => 'Таймаут неактивности части-сироты (минут)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.default_quota_bytes' => 'Квота хранилища по умолчанию (байт)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.base_owned_workspace_slots' => 'Базовые слоты собственных рабочих областей',
        'archivd.disabled_regions' => 'Отключённые регионы хранилища',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.signed_download_ttl_minutes' => 'Срок действия подписанного URL скачивания (минут)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.signed_download_cache_margin_seconds' => 'Запас кэша подписанного скачивания (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.public_download_signature_ttl_minutes' => 'Срок действия подписи публичного скачивания (минут)',
        'archivd.allowed_mime_prefixes' => 'Разрешённые префиксы MIME-типов',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'archivd.download_vault.enabled' => 'Прокси хранилища скачиваний включён',
        // Microstock
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.daily_free.enabled' => 'Ежедневные бесплатные скачивания включены',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.daily_free.limit_per_user' => 'Лимит ежедневных бесплатных скачиваний на пользователя',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.daily_free.timezone' => 'Часовой пояс сброса ежедневного лимита',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai_billing.markup_multiplier' => 'Множитель наценки ИИ-оплаты',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai_billing.estimated_billable_micro_usd_per_asset' => 'Расчётные тарифицируемые микро-USD на актив',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai_billing.estimate_thinking_attempt_factor' => 'Коэффициент оценки попыток мышления',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.image_max_px' => 'Максимальный размер изображения ИИ (px)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.image_jpeg_quality' => 'Качество JPEG-изображения ИИ',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.max_request_image_bytes' => 'Максимальный размер изображения запроса (байт)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.http_retries' => 'Повторы HTTP-запросов ИИ',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.thinking_visual_attempts' => 'Визуальные попытки мышления',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.batch_chunk' => 'Размер фрагмента пакета ИИ',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.batch_min_files' => 'Минимум файлов в пакете ИИ',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.phase.normal_after' => 'Фаза ИИ: переход в обычный режим после (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.phase.grace_after' => 'Фаза ИИ: льготный режим после (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.stage.vision_grace_after' => 'Стадия ИИ: льготный режим распознавания после (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.stage.thinking_grace_after' => 'Стадия ИИ: льготный режим мышления после (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.stage.thinking_backlog_after' => 'Стадия ИИ: переход задач мышления в очередь отложенных после (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.vision_image.delivery' => 'Режим доставки изображения распознавания',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.vision_image.variant_kind' => 'Тип варианта изображения распознавания',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.vision_image.signed_url_ttl_minutes' => 'Срок действия подписанного URL распознавания (минут)',
        'microstock.ai.inference_provider_slug' => 'Slug провайдера инференса Microstock',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.vision.connection' => 'Подключение ИИ для распознавания (пусто = по умолчанию)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.thinking.connection' => 'Подключение ИИ для мышления (пусто = по умолчанию)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.vision.model' => 'Модель распознавания',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.vision.free_model' => 'Модель распознавания бесплатного уровня (пусто = платная модель)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.vision.timeout' => 'HTTP-таймаут распознавания (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.thinking.model' => 'Модель мышления',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.thinking.free_model' => 'Модель мышления бесплатного уровня (пусто = платная модель)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.thinking.timeout' => 'HTTP-таймаут мышления (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.upstream.window_seconds' => 'Окно бюджета токенов провайдера (секунд)',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.upstream.token_budget' => 'Бюджет токенов провайдера на окно',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.upstream.max_concurrent_vision' => 'Максимум одновременных запросов распознавания',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.upstream.max_concurrent_thinking' => 'Максимум одновременных запросов мышления',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.upstream.collection_vision_concurrency' => 'Параллелизм распознавания коллекций',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.upstream.collection_thinking_concurrency' => 'Параллелизм мышления коллекций',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.upstream.solo_vision_concurrency' => 'Параллелизм распознавания одиночных',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.upstream.solo_thinking_concurrency' => 'Параллелизм мышления одиночных',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.upstream.release_after_seconds' => 'Освобождение слота провайдера после (секунд)',
        'microstock.csv_disk' => 'Диск экспорта CSV',
    ],
    'hints' => [
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.mail.from_address' => 'Глобальный адрес отправителя для исходящей почты (транспортные учётные данные остаются в .env).',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.payment.default_buyer_phone' => 'Резервный телефон, когда в профиле покупателя нет номера (требование iPaymu).',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.frankfurter.timeout' => 'HTTP-таймаут для запросов курсов Frankfurter FX.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.vision.free_model' => 'Необязательная замена модели бесплатного уровня; пусто наследует платную модель распознавания.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.thinking.free_model' => 'Необязательная замена модели бесплатного уровня; пусто наследует платную модель мышления.',
        // curated at 2026-09-23T19:29:12+07:00 — ru curation wave: settled terms
        'microstock.ai.vision.connection' => 'Слаг ИИ-подключения (Настройки → Подключения ИИ) для этапа распознавания; пусто использует подключение по умолчанию.',
        // curated at 2026-09-23T19:29:12+07:00 — ru curation wave: settled terms
        'microstock.ai.thinking.connection' => 'Слаг ИИ-подключения (Настройки → Подключения ИИ) для этапа мышления; пусто использует подключение по умолчанию.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.upstream.window_seconds' => 'Эталонное окно бюджета токенов провайдера (ограничение — через пределы параллелизма).',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.jwt.refresh_skew' => 'Обновлять токен доступа за столько минут до его истечения.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.notifications.delivery' => 'Режим реального времени использует WebSockets и требует настроенных учётных данных PUSHER_* / broadcast; иначе колокольчик переключается на HTTP-опрос.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.payment.channel_fee_bearer' => 'Кто берёт на себя комиссию платёжного канала: продавец или покупатель.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.inference.default_provider_slug' => 'Резервный провайдер, если в запросе не указан другой.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.inference.default_input_per_million_usd' => 'Временная ставка оплаты для зарегистрированных моделей без каталожных цен.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'platform.inference.unknown_input_per_million_usd' => 'Временная ставка оплаты для незарегистрированных моделей.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.phase.normal_after' => 'Секунды до перехода актива из ускоренной обработки в обычную.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.phase.grace_after' => 'Секунды до начала обработки льготного периода.',
        // curated at 2026-09-23T19:29:12+07:00 — ru curation wave: settled terms
        'microstock.ai.stage.vision_grace_after' => 'Секунды до перехода стадии распознавания в льготный режим.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.stage.thinking_grace_after' => 'Секунды до перехода стадии мышления в льготный режим.',
        // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
        'microstock.ai.stage.thinking_backlog_after' => 'Секунды до перемещения заданий мышления в очередь отложенных задач.',
        // curated at 2026-09-27T19:30:00+07:00 — ru curation wave
        'identity.discord.connections_snapshot_max_age_minutes' => 'Как долго сохранённые снимки подключений Discord считаются достоверными, прежде чем промах проверки вызовет живое обновление через Discord API.',
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
