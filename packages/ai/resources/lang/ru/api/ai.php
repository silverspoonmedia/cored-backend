<?php

return [
    'connection' => [
        // curated at 2026-09-23T19:26:40+07:00 — ru curation wave: settled terms
        'list_success' => 'Подключения ИИ получены.',
        // curated at 2026-09-23T19:26:40+07:00 — ru curation wave: settled terms
        'show_success' => 'Подключение ИИ получено.',
        // curated at 2026-09-23T19:26:40+07:00 — ru curation wave: settled terms
        'created' => 'Подключение ИИ создано. Запустите проверку, чтобы активировать его.',
        // curated at 2026-09-23T19:26:40+07:00 — ru curation wave: settled terms
        'updated' => 'Подключение ИИ обновлено. Запустите проверку снова, чтобы активировать его.',
        // curated at 2026-09-23T19:26:40+07:00 — ru curation wave: settled terms
        'deleted' => 'Подключение ИИ удалено.',
        // curated at 2026-09-23T19:26:40+07:00 — ru curation wave: settled terms
        'default_updated' => 'Подключение ИИ по умолчанию обновлено.',
        // curated at 2026-09-23T19:26:40+07:00 — ru curation wave: settled terms
        'slug_required' => 'Слаг подключения обязателен.',
        // curated at 2026-09-23T19:26:40+07:00 — ru curation wave: settled terms
        'slug_taken' => 'Этот слаг подключения уже используется.',
        'label_required' => 'Название подключения обязательно.',
        'endpoint_invalid' => 'Эндпоинт должен быть корректным http(s) URL.',
        'test_model_required' => 'Для проверки подключения нужна тестовая модель.',
        'payload_invalid' => 'Сохранённый секрет некорректен. Введите API-ключ заново.',
        'payload_missing' => 'В секрете отсутствует обязательное поле: :field.',
        'payload_unknown_key' => 'Неизвестное поле секрета: :field.',
        'default_requires_active' => 'Подключением по умолчанию может стать только активное (проверенное).',
        'delete_requires_default_handover' => 'Назначьте другое подключение по умолчанию, прежде чем удалять это.',
        'test_not_configured' => 'У подключения нет эндпоинта, API-ключа или тестовой модели.',
        'test_unreachable' => 'Не удалось связаться с эндпоинтом: :error',
        'test_unauthorized' => 'Эндпоинт отклонил API-ключ (401/403).',
        'test_http_failed' => 'Эндпоинт ответил HTTP :status.',
        'test_http_failed_with_body' => 'Эндпоинт ответил HTTP :status: :detail',
        'test_passed' => 'Проверка подключения пройдена.',
    ],
];
