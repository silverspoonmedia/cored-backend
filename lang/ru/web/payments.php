<?php

return [
    'gateways' => [
        'title' => 'Платёжные шлюзы',
        'list_title' => 'Список платёжных шлюзов',
        'create' => 'Добавить шлюз',
        'create_title' => 'Создать платёжный шлюз',
        'edit_title' => 'Редактировать платёжный шлюз',
        'form' => [
            'name' => 'Системное имя',
            'display_name' => 'Отображаемое имя',
            'priority' => 'Приоритет',
            'is_active' => 'Активен',
        ],
        'confirm_delete' => 'Удалить этот платёжный шлюз?',
        'datatable' => [
            'empty' => 'Платёжные шлюзы не настроены.',
            'zero_records' => 'По вашему запросу платёжные шлюзы не найдены.',
            'columns' => [
                'name' => 'Имя',
                'display_name' => 'Отображаемое имя',
                'is_active' => 'Активен',
                'priority' => 'Приоритет',
            ],
        ],
    ],

    'show' => [
        'payment_not_started' => 'Способ оплаты ещё не выбран',
        'payment_pending' => 'Ожидает оплаты',
        'status' => [
            'paid' => 'Оплачен',
            'expired' => 'Истёк',
            'cancelled' => 'Отменён',
            // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
            'failed' => 'Не удалось',
        ],
        'payment_steps' => [
            'aria' => 'Шаги оплаты',
            'choose_channel' => 'Выберите способ',
            'choose_channel_hint' => 'Выберите QRIS, банковский перевод или электронный кошелёк.',
            'pay' => 'Оплатить',
            'pay_hint' => 'Завершите оплату по показанным инструкциям.',
            'confirm' => 'Подтверждение',
            'confirm_hint' => 'Статус обновится после подтверждения оплаты провайдером.',
        ],
    ],

    'invoices' => [
        'title' => 'Счета',
        'list_title' => 'Список счетов',
        'show_title' => 'Детали счёта',
        'show_card_title' => 'Счёт :number',
        'view_order' => 'Открыть заказ',
        'view_purchase' => 'Открыть покупку услуги',
        'back_to_list' => 'Назад к счетам',
        'datatable' => [
            'empty' => 'Счета не найдены.',
            'zero_records' => 'По вашему запросу счета не найдены.',
            'columns' => [
                // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
                'invoice_number' => 'Счёт №',
                'status' => 'Статус',
                'subtotal' => 'Стоимость услуги',
                'fee' => 'Комиссия за оплату',
                'total' => 'Итого для клиента',
                // curated at 2026-09-23T20:26:04+07:00 — ru curation wave: date labels as nouns
                'created_at' => 'Дата создания',
                'actions' => 'Действия',
            ],
        ],
        'export_csv' => 'Экспорт CSV',
        'fields' => [
            'invoice_number' => 'Номер счёта',
            'status' => 'Статус',
            'payment_method' => 'Способ оплаты',
            'gateway' => 'Шлюз',
            'subtotal' => 'Стоимость услуги',
            'fee' => 'Комиссия за оплату (покупатель)',
            'total' => 'Итого для клиента',
            'currency' => 'Валюта',
            // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
            'paid_at' => 'Оплачено',
            // curated at 2026-09-23T20:26:04+07:00 — ru curation wave: date labels as nouns
            'expired_at' => 'Действует до',
            // curated at 2026-09-23T20:26:04+07:00 — ru curation wave: date labels as nouns
            'created_at' => 'Дата создания',
            // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
            'payment_route' => 'Маршрут оформления заказа',
        ],
    ],

    'income' => [
        'title' => 'Доход от платежей',
        'subtitle' => 'Выручка и денежные поступления по оплаченным счетам. Стоимость услуги — ваш доход от продукта; комиссии за оплату — доплаты покупателя, передаваемые платёжному провайдеру.',
        'loading' => 'Загрузка…',
        'load_failed' => 'Не удалось загрузить данные о доходах.',
        'empty_invoices' => 'Нет оплаченных счетов за этот период.',
        'empty_breakdown' => 'Нет данных за этот период.',
        'invoices_title' => 'Оплаченные счета',
        'pagination' => 'Страница :page из :total',
        'filters' => [
            'from' => 'С',
            'to' => 'По',
            'basis' => 'Основа даты',
            'basis_paid_at' => 'Дата оплаты (paid_at)',
            'basis_withdrawable_at' => 'Дата расчёта (withdrawable_at)',
            'gateway' => 'Шлюз',
            'all_gateways' => 'Все шлюзы',
            'apply' => 'Применить',
        ],
        'summary' => [
            'service_revenue' => 'Выручка за услуги',
            'service_revenue_hint' => 'Сумма стоимости услуг по счетам (ваши продажи продукта).',
            'buyer_fees' => 'Комиссии покупателей за оплату',
            'buyer_fees_hint' => 'Доплаты по каналам, собранные с клиентов.',
            // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
            'cash_collected' => 'Получено средств',
            'cash_collected_hint' => 'Всего оплачено клиентами (стоимость услуги + комиссии).',
            'invoice_count' => 'Оплаченные счета',
            'avg_order_value' => 'Средний чек',
        ],
        'pipeline' => [
            'title' => 'Движение средств',
            'invoices' => 'счетов',
            'awaiting_settlement' => 'Ожидают расчёта от шлюза',
            'ready_to_disburse' => 'Готовы к выплате',
            'in_payout' => 'В выплате',
            'disbursed' => 'Выплачено в банк',
            'failed_payout' => 'Неудачная выплата',
        ],
        'breakdown' => [
            'gateway' => 'По шлюзам',
            'channel' => 'По каналам',
            'product' => 'По типу продукта',
            'label' => 'Название',
            'invoices' => 'Счета',
            'service_revenue' => 'Выручка за услуги',
            // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
            'cash_collected' => 'Получено средств',
        ],
        'daily' => [
            'title' => 'Дневная динамика',
            'date' => 'Дата',
            'invoices' => 'Счета',
            'service_revenue' => 'Выручка за услуги',
            // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
            'cash_collected' => 'Получено средств',
        ],
    ],

    'subscriptions' => [
        'title' => 'Подписки',
        'list_title' => 'Список подписок',
        'create_title' => 'Добавить подписку',
        'edit_title' => 'Редактировать подписку',
        'show_title' => 'Детали подписки',
        'show_card_title' => 'Подписка :number',
        'add_button' => 'Добавить подписку',
        'new_card_title' => 'Новая подписка',
        'edit_card_title' => 'Редактировать подписку: :number',
        'back_to_list' => 'Назад к подпискам',
        'datatable' => [
            'empty' => 'Подписки не найдены.',
            'zero_records' => 'По вашему запросу подписки не найдены.',
            'columns' => [
                // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
                'subscription_number' => 'Подписка №',
                'subscriber_name' => 'Подписчик',
                'plan_name' => 'Тариф',
                'status' => 'Статус',
                'amount' => 'Сумма',
                'actions' => 'Действия',
            ],
        ],
        'fields' => [
            'subscriber_name' => 'Имя подписчика',
            'subscriber_email' => 'Email подписчика',
            'subscriber_phone' => 'Телефон подписчика',
            'plan_name' => 'Название тарифа',
            'plan_description' => 'Описание тарифа',
            // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
            'billing_cycle' => 'Период оплаты',
            'amount' => 'Сумма',
            'currency' => 'Валюта',
            'status' => 'Статус',
            'auto_renew' => 'Автопродление',
            // curated at 2026-09-23T20:26:04+07:00 — ru curation wave: date labels as nouns
            'started_at' => 'Дата начала',
            // curated at 2026-09-23T20:26:04+07:00 — ru curation wave: date labels as nouns
            'expires_at' => 'Действует до',
            // curated at 2026-09-23T19:26:43+07:00 — ru curation wave: settled terms
            'trial_ends_at' => 'Конец пробного периода',
            // curated at 2026-09-23T20:26:04+07:00 — ru curation wave: date labels as nouns
            'created_at' => 'Дата создания',
        ],
    ],

    'confirm' => [
        'delete_subscription' => 'Удалить эту подписку?',
    ],

    'ipaymu_return' => [
        'invoice_not_found' => 'Не удалось сопоставить этот платёж со счётом.',
        'paid' => 'Платёж получен. Ваш счёт отмечен как оплаченный.',
        'pending' => 'Платёж обрабатывается. Обновите страницу через некоторое время, если статус не изменился.',
        'failed' => 'Платёж не выполнен или отклонён.',
        'cancelled' => 'Платёж отменён.',
        'expired' => 'Сессия оплаты истекла.',
    ],

    'checkout' => [
        'title' => 'Оплата',
        'invoice_summary' => 'Счёт :number',
        'amount' => 'Сумма к оплате',
        'subtotal' => 'Стоимость услуги',
        'payment_fee' => 'Комиссия за оплату',
        'payment_fee_note' => 'Оплачивается платёжному провайдеру и добавляется к вашей сумме.',
        'total_due' => 'Итого к оплате',
        'fee_estimate_hint' => 'Оценочная комиссия — итоговая сумма подтверждается при переходе к оплате.',
        'fee_percent_basis' => ':percent от стоимости услуги',
        'fee_at_checkout_hint' => 'Комиссия платёжного провайдера добавляется при выборе способа оплаты.',
        'routing_updated' => 'Способы оплаты обновлены. Комиссии могли измениться.',
        'channel_unavailable' => ':channel больше недоступен.',
        'fee_updated' => 'Комиссия :channel изменилась с :old на :new.',
        // curated at 2026-09-23T20:02:56+07:00 — ru curation wave: drop Пожалуйста filler
        'selected_channel_unavailable' => 'Выбранный способ оплаты больше недоступен. Выберите другой.',
        'pay_now' => 'Оплатить сейчас',
        'snap_loading' => 'Открытие оплаты…',
        // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
        'snap_failed' => 'Не удалось начать оформление заказа',
        // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
        'started' => 'Сессия оформления заказа создана.',
        'paid' => 'Этот счёт уже оплачен.',
        'not_payable' => 'Этот счёт нельзя оплатить в текущем состоянии.',
        // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
        'unsupported_gateway' => 'Платёжный шлюз счёта не поддерживает хостинговое оформление заказа.',
        'select_channel' => 'Способ оплаты',
        // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
        'cheap_alt_hint' => 'Оплатите через :channel, чтобы сэкономить на комиссии (отсканируйте QR при оформлении заказа).',
        'pay_with_channel' => 'Перейти к оплате',
        'channel_loading' => 'Создание платежа…',
        'channel_ready' => 'Завершите оплату по инструкциям ниже.',
        // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
        'channel_failed' => 'Не удалось начать оформление заказа по каналу',
        'confirm_paid' => 'Я оплатил',
        'confirm_paid_help' => 'Если вы завершили оплату вне этого экрана (например, наличными или банковским переводом), нажмите здесь, чтобы повторно проверить статус счёта.',
        'change_channel' => 'Изменить платёжный канал',
        'no_channels' => 'Для этого счёта нет доступных способов оплаты.',
        'permission_required' => 'У вас нет разрешения на начало платежа. Запросите у администратора разрешение «initiate payment».',
        'synced' => 'Статус платежа синхронизирован.',
        'syncing' => 'Синхронизация…',
        'sync_pending' => 'Платёж всё ещё ожидает оплаты.',
        'sync_failed' => 'Не удалось синхронизировать статус платежа.',
    ],

    'instructions' => [
        'title' => 'Инструкции по оплате',
        'expires_at' => 'Оплатите до :time',
        'qr_string' => 'QR-данные',
        'bank' => 'Банк',
        'va_number' => 'Виртуальный счёт',
        'account_name' => 'Имя владельца счёта',
        'open_ewallet' => 'Открыть приложение электронного кошелька',
        'copy' => 'Копировать',
        'copied' => 'Скопировано в буфер обмена',
        'copy_failed' => 'Не удалось скопировать',
        'pending_hint' => 'Статус обновляется автоматически после подтверждения оплаты платёжным провайдером.',
    ],

    'channels' => [
        'title' => 'Платёжные каналы',
        'list_title' => 'Список платёжных каналов',
        'updated' => 'Платёжный канал обновлён.',
        'route_updated' => 'Маршрут платёжного канала обновлён.',
        'routes_title' => 'Маршруты шлюзов',
        // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
        'routes_lead' => 'Оформление заказа сначала пробует основные или единственные маршруты, затем резервные. В рамках одной роли сначала пробуется меньший номер приоритета. Неактивные маршруты или отсутствующие учётные данные шлюза пропускаются.',
        'empty' => 'Платёжные каналы не настроены. Выполните php artisan payments:sync-routing-policy после PaymentGatewaySeeder.',
        'no_routes' => 'К этому каналу не привязаны маршруты шлюзов.',
        'no_results' => 'По вашему запросу каналы не найдены.',
        'search_label' => 'Поиск каналов',
        'search_placeholder' => 'Поиск по имени, коду или типу…',
        // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
        'inactive_channel' => 'Скрыт при оформлении заказа',
        'primary_route' => 'Пробуется первым',
        'priority_hint' => 'Меньшее число = выше приоритет в рамках одной роли маршрута. Приоритеты, управляемые политикой, нельзя редактировать здесь.',
        'priority_autosave_hint' => 'сохраняется автоматически',
        'visible_count' => '{0} Нет каналов|{1} Виден :count канал|[2,*] Видно каналов: :count',
        'route_count' => '{0} Нет маршрутов|{1} :count маршрут|[2,*] Маршрутов: :count',
        'status' => [
            'active' => 'Активен',
            'inactive' => 'Неактивен',
        ],
        'kinds' => [
            'qris' => 'QRIS',
            'va' => 'Виртуальный счёт',
            'ewallet' => 'Электронный кошелёк',
        ],
        'intro' => [
            // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
            'title' => 'Как работает маршрутизация каналов при оформлении заказа',
            'lead' => 'Клиенты выбирают логический канал (QRIS, банковский виртуальный счёт, электронный кошелёк). Затем Monolith пробует маршруты шлюзов в порядке политики (роль маршрута, затем приоритет), пока не будут созданы инструкции по оплате.',
            'steps' => [
                [
                    'title' => 'Включите канал',
                    // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
                    'body' => 'Неактивные каналы скрыты при оформлении заказа. Маршруты могут оставаться настроенными, пока канал выключен.',
                ],
                [
                    'title' => 'Роли маршрутов',
                    'body' => 'Основные или единственные маршруты пробуются раньше резервных. Приоритет (10 раньше 20) действует в рамках одной роли. Комиссия и расчёт берутся из Routing_Price.yaml.',
                ],
                [
                    // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
                    'title' => 'Резерв при оформлении заказа',
                    'body' => 'Если все доступные маршруты не сработали или учётные данные отсутствуют, оформление заказа может предложить хостинговый Snap или перенаправление iPaymu.',
                ],
            ],
        ],
        'stats' => [
            'channels_active' => 'Активные каналы',
            'routes_active' => 'Активные маршруты',
            // curated at 2026-09-23T17:26:06+07:00 — ru curation wave
            'checkout_hint' => 'Поведение при оформлении заказа',
            'checkout_body' => 'Плательщикам видны только активные каналы хотя бы с одним рабочим маршрутом.',
        ],
        'columns' => [
            'code' => 'Код',
            'display_name' => 'Отображаемое имя',
            'kind' => 'Тип',
            'sort_order' => 'Порядок сортировки',
            'is_active' => 'Активен',
        ],
        'route_columns' => [
            'gateway' => 'Шлюз',
            'provider_type' => 'Тип провайдера',
            'provider_channel' => 'Канал провайдера',
            'priority' => 'Приоритет',
            'is_active' => 'Активен',
            'route_role' => 'Роль',
            'settlement' => 'Расчёт',
            'fee' => 'Комиссия',
            'policy_managed' => 'Управляется политикой',
        ],
        'route_roles' => [
            'primary' => 'Основной',
            'fallback' => 'Резервный',
            'only' => 'Единственный',
        ],
        'sync_policy' => 'Синхронизировать политику маршрутизации',
        'sync_policy_hint' => 'Перезагрузить каналы и маршруты из Routing_Price.yaml.',
        'sync_policy_done' => 'Политика маршрутизации синхронизирована (каналов: :channels, маршрутов: :routes). Перезагрузка…',
    ],
];
