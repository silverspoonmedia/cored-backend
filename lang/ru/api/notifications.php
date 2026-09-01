<?php

return [
    'list_success' => 'Уведомления загружены.',
    'unread_count_success' => 'Количество непрочитанных уведомлений получено.',
    'marked_read' => 'Уведомление отмечено прочитанным.',
    'marked_all_read' => 'Все уведомления отмечены прочитанными.',
    'not_visible' => 'У вас нет прав доступа к этому уведомлению.',
    // Message templates per notification type. Replacements come from the
    // notification `data` payload (:name, :email, :actor, :role, …).
    'types' => [
        'user_created' => 'Новый пользователь :name (:email) добавлен пользователем :actor.',
        'user_updated' => ':actor обновил пользователя :name (:email).',
        'user_deleted' => ':actor удалил пользователя :name (:email).',
        'account_updated' => 'Ваш аккаунт был обновлён пользователем :actor.',
        'service_subscription_grace' => 'Ваша подписка на :service закончилась. Продлите её в течение :grace_days дн. (до :grace_ends_at), иначе избыточные файлы будут удалены до лимитов бесплатного тарифа.',
        'service_subscription_expired' => 'Льготный период вашей подписки на :service истёк. Применяются лимиты бесплатного тарифа (удалено файлов: :deleted).',
        'inference_unregistered_model' => 'Незарегистрированная модель :resolved_model у :provider_slug тарифицирована по предварительным ставкам ($:input_per_million_usd / $:output_per_million_usd за 1M). Классифицируйте её в каталоге моделей для окончательного расчёта.',
    ],
    'audiences' => [
        'list_success' => 'Аудитории уведомлений загружены.',
        'show_success' => 'Аудитория уведомлений загружена.',
        'updated' => 'Аудитория уведомлений обновлена.',
        'unknown_type' => 'Неизвестный тип уведомления.',
    ],
];
