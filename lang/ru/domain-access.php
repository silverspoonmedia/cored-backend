<?php

declare(strict_types=1);

return [
    // Gate / middleware
    'auth_required' => 'Требуется аутентификация.',
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'unknown_product' => 'Неизвестный домен продукта: :product.',
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'forbidden' => 'Этот домен продукта приватный. Доступ ограничен для Root, Admin и его бета-тестеров.',

    // Admin API
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'list_success' => 'Настройки доступа к доменам продуктов получены.',
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'updated' => 'Видимость домена продукта обновлена.',
    'invalid_visibility' => 'Видимость должна быть public или private.',

    // UI
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'title' => 'Доступ к доменам продуктов',
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'subtitle' => 'Управляйте тем, какие домены продуктов публичны, а какие доступны только бета-тестерам.',
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'empty' => 'Домены продуктов не зарегистрированы.',
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'col_domain' => 'Домен продукта',
    'col_visibility' => 'Видимость',
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'visibility_public' => 'Публичный',
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'visibility_private' => 'Приватный',
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'public_hint' => 'Этот домен продукта доступен всем.',
    'private_hint' => 'Доступ только для Root, Admin и бета-тестеров.',
    // curated at 2026-09-23T17:10:06+07:00 — ru curation wave — product domain wording, public/private labels
    'beta_role' => 'Роль бета-тестировщика: :role',
    'toggle_updated' => 'Видимость обновлена.',
    'toggle_failed' => 'Не удалось обновить видимость.',
];
