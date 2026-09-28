<?php

return [
    'view_profile' => 'Просмотреть профиль',
    'view_detail' => 'Просмотр',
    'manage' => 'Управление',
    'test' => 'Проверить',
    'revoke' => 'Отозвать',
    'make_default' => 'Сделать по умолчанию',
    'activate' => 'Активировать',
    'deactivate' => 'Деактивировать',
    'set_settlement' => 'Назначить расчётным',
    'remove' => 'Убрать',
    // curated at 2026-09-25T21:30:00+07:00 — vTual claims hub row actions
    'verify_claim' => 'Проверить заявку',
    'accept_anyway' => 'Принять без проверки',
    // curated at 2026-09-27T19:20:00+07:00 — vTual incident triage queue row actions
    'acknowledge_incident' => 'Принять к работе',
    'resolve_incident' => 'Отметить решённым',
    'open_credential' => 'Открыть учётные данные',
    'open_profile' => 'Открыть профиль',
    'confirm' => [
        // curated at 2026-09-23T19:58:41+07:00 — ru curation wave: management entity settled on организация
        'archive_management' => 'Архивировать эту организацию?',
        // curated at 2026-09-25T21:30:00+07:00 — vTual claims hub row actions
        'cancel_claim_title' => 'Отмена заявки',
        'cancel_claim' => 'Отменить эту заявку? Токен в описании канала перестанет действовать, и заявку придётся начать заново.',
        'accept_anyway_claim_title' => 'Принятие без проверки',
        'accept_anyway_claim' => 'Принять эту заявку, не найдя токен в описании канала?',
        'cancel_event' => 'Отменить это событие?',
        'delete_service' => 'Удалить этот сервис?',
        'delete_inference' => 'Удалить эту запись? Это действие нельзя отменить.',
        'delete_named_title' => 'Удалить «:name»',
        'delete_menu' => 'Вы уверены, что хотите удалить «:name»?',
        'delete_post' => 'Эта запись будет перемещена в корзину. Её можно будет восстановить позже.',
        'delete_microstock_credential' => 'Удалить эти учётные данные?',
        'delete_credential_title' => 'Удаление учётных данных',
        'delete_platform_credential' => 'Удалить эти учётные данные безвозвратно?',
        'revoke_credential_title' => 'Отзыв учётных данных',
        'revoke_credential' => 'Отозвать эти учётные данные? Воркеры перестанут их использовать, пока вы не отредактируете и не нажмёте «Проверить» снова.',
        'delete_ai_connection_title' => 'Удалить подключение',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'delete_ai_connection' => 'Удалить это подключение ИИ навсегда? Потребители, привязанные к его слагу, потеряют эндпоинт, пока вы не привяжете другое.',
        'remove_blocklist_title' => 'Удалить запись',
        'remove_blocklist' => 'Убрать этот хеш из чёрного списка? Файлы с этой контрольной суммой снова смогут быть общедоступными.',
        // curated at 2026-09-27T19:20:00+07:00 — vTual incident triage queue row actions
        'resolve_incident_title' => 'Закрытие инцидента',
        'resolve_incident' => 'Отметить инцидент решённым? При повторном сбое он откроется снова автоматически.',
    ],
];
