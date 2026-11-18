<?php

return [
    // curated at 2026-09-23T19:29:11+07:00 — ru curation wave: settled terms
    'success' => 'Платёж завершён.',
    // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
    'failed' => 'Платёж не выполнен. Повторите попытку.',
    // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
    'pending' => 'Платёж обрабатывается.',

    'gateways' => [
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'list' => 'Список платёжных шлюзов получен.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'show' => 'Платёжный шлюз получен.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'created' => 'Платёжный шлюз создан.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'updated' => 'Платёжный шлюз обновлён.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'deleted' => 'Платёжный шлюз удалён.',
        'inactive' => 'Выбранный платёжный шлюз не активен.',
        'none_active' => 'Активный платёжный шлюз не настроен.',
    ],

    'invoices' => [
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'list' => 'Список счетов получен.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'show' => 'Счёт получен.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'checkout_channels' => 'Каналы оформления заказа загружены.',
        'checkout_channels_not_payable' => 'Счёт не открыт для оплаты.',
    ],

    'income' => [
        'overview' => 'Обзор платёжных поступлений загружен.',
        'invoices' => 'Счета платёжных поступлений загружены.',
    ],

    'subscriptions' => [
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'list' => 'Список подписок получен.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'show' => 'Подписка получена.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'created' => 'Подписка создана.',
        // curated at 2026-09-23T19:29:11+07:00 — ru curation wave: settled terms
        'updated' => 'Подписка обновлена.',
        // curated at 2026-09-23T19:29:11+07:00 — ru curation wave: settled terms
        'deleted' => 'Подписка удалена.',
    ],

    'midtrans' => [
        'snap_created' => 'Транзакция Snap создана.',
        'charge_created' => 'Запрос на списание через Core API создан.',
        'status_retrieved' => 'Статус транзакции получен.',
        'cancel_ok' => 'Транзакция отменена.',
        'expire_ok' => 'Транзакция истекла.',
        'refund_ok' => 'Возврат обработан.',
        'approve_ok' => 'Транзакция одобрена.',
        'deny_ok' => 'Транзакция отклонена.',
        'sync_ok' => 'Статус счёта синхронизирован с Midtrans.',
        'invoice_not_midtrans' => 'Счёт не привязан к шлюзу Midtrans.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'invoice_not_payable' => 'Счёт должен быть в статусе pending или challenge, чтобы начать оплату.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'external_sync_failed' => 'Не удалось проверить статус оплаты в Midtrans перед началом оформления заказа.',
        'webhook_invalid_json' => 'Недопустимое тело JSON.',
        'webhook_invalid_signature' => 'Недопустимая подпись уведомления.',
        'webhook_missing_order' => 'В уведомлении отсутствует order_id.',
        'webhook_ack_no_status' => 'Уведомление принято (статус не удалось определить).',
        'webhook_orphan' => 'Уведомление принято (подходящий счёт не найден).',
        'webhook_ok' => 'Уведомление обработано.',
        'webhook_ping' => 'URL webhook Midtrans доступен (для уведомлений используйте POST).',
        'webhook_subscription_ack' => 'Уведомление о подписке принято.',
        'open_api_public_key_missing' => 'Публичный ключ Midtrans Open API не настроен.',
        'open_api_signature_invalid' => 'Недопустимая или непроверяемая подпись X-SIGNATURE для уведомления Open API.',
    ],

    'checkout' => [
        'active_session_pending' => 'Предыдущая платёжная сессия ещё ожидает обработки у провайдера. Дождитесь её истечения или завершения, прежде чем менять канал.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'gateway_provider_mismatch' => 'Платёжный шлюз счёта не совпадает с запрошенным провайдером оформления заказа.',
    ],

    'channels' => [
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'list' => 'Список платёжных каналов получен.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'show' => 'Платёжный канал получен.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'updated' => 'Платёжный канал обновлён.',
        'checkout_created' => 'Инструкции по оплате созданы.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'checkout_failed' => 'Все маршруты оплаты для этого канала завершились ошибкой.',
        'route_updated' => 'Маршрут платёжного канала обновлён.',
        'not_found' => 'Платёжный канал не найден.',
        'not_available' => 'Выбранный платёжный канал недоступен.',
        'unsupported_gateway' => 'Для этого канала не настроен поддерживаемый платёжный шлюз.',
        'policy_managed_route' => 'Этот маршрут управляется политикой маршрутизации. Можно изменить только активацию.',
    ],

    'ipaymu' => [
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'snap_created' => 'Сессия оформления заказа iPaymu создана.',
        'charge_created' => 'Прямой платёж iPaymu создан.',
        'status_retrieved' => 'Статус транзакции получен от iPaymu.',
        'sync_ok' => 'Статус счёта синхронизирован с iPaymu.',
        'invoice_not_ipaymu' => 'Счёт не привязан к шлюзу iPaymu.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'invoice_not_payable' => 'Счёт должен быть в статусе pending или challenge, чтобы начать оплату.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'external_sync_failed' => 'Не удалось проверить статус оплаты в iPaymu перед началом оформления заказа.',
        // curated at 2026-09-23T19:26:41+07:00 — ru curation wave: settled terms
        'active_payment_exists' => 'Для этого счёта уже идёт платёжная сессия. Завершите или отмените её, прежде чем начинать новое оформление заказа.',
        'invoice_not_found' => 'Счёт iPaymu для этого идентификатора заказа не найден.',
        'missing_transaction_id' => 'У счёта ещё нет идентификатора транзакции iPaymu; сначала завершите платёжную сессию.',
        'buyer_required' => 'Не удалось определить данные покупателя из запроса, плательщика счёта или профиля аутентифицированного пользователя.',
        'webhook_invalid_json' => 'Недопустимое тело JSON.',
        'webhook_invalid_signature' => 'Недопустимая подпись уведомления.',
        'webhook_invalid_va' => 'Заголовок VA не совпадает с настроенным iPaymu VA.',
        'webhook_ack_no_status' => 'Уведомление принято (статус не удалось определить).',
        'webhook_orphan' => 'Уведомление принято (подходящий счёт не найден).',
        'webhook_ok' => 'Уведомление обработано.',
        'webhook_ping' => 'URL webhook iPaymu доступен (для уведомлений используйте POST).',
    ],
];
