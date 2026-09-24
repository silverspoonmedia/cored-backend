<?php

declare(strict_types=1);

return [
    'list_success' => 'Активации продуктов получены.',
    'show_success' => 'Активация продукта получена.',
    'admin_list_success' => 'Активации продуктов получены.',
    'stats_success' => 'Статистика активаций продуктов получена.',
    'suspended' => 'Активация продукта приостановлена.',
    // curated at 2026-09-23T17:11:09+07:00 — ru curation wave — reactivate vs restore, product domain noun, workspace term
    'reactivated' => 'Активация продукта возобновлена.',
    'approved' => 'Активация продукта одобрена.',
    'rejected' => 'Активация продукта отклонена.',

    // curated at 2026-09-23T17:11:09+07:00 — ru curation wave — reactivate vs restore, product domain noun, workspace term
    'not_found' => 'Активация продукта :product не найдена.',
    'unknown_product' => 'Неизвестный ключ продукта: :product.',
    'already_active' => 'Продукт уже активен.',
    'cannot_approve' => 'Нельзя одобрить активацию в состоянии :state.',
    'cannot_reject' => 'Нельзя отклонить активацию в состоянии :state.',
    'cannot_suspend' => 'Нельзя приостановить активацию в состоянии :state.',
    // curated at 2026-09-23T17:11:09+07:00 — ru curation wave — reactivate vs restore, product domain noun, workspace term
    'cannot_reactivate' => 'Нельзя возобновить активацию в состоянии :state.',
    'type_mismatch' => 'Продукт :product ожидает тип активации :expected, получен :actual.',
    'inactive' => 'Продукт :product в состоянии :state — доступ запрещён.',
    'not_provisioned' => 'Продукт :product ещё не подготовлен.',
    'auth_required' => 'Требуется аутентификация.',

    // UI labels (Blade)
    'title' => 'Активации продуктов',
    'subtitle' => 'Реестр активаций продуктов по пользователям.',
    'col_user' => 'Пользователь',
    'col_product' => 'Продукт',
    'col_type' => 'Тип',
    'col_state' => 'Состояние',
    'col_source' => 'Источник',
    'col_activated_at' => 'Активировано',
    'col_suspended_at' => 'Приостановлено',
    'col_expired_at' => 'Истекло',
    'col_updated_at' => 'Обновлено',
    'col_actions' => 'Действия',
    'filter_product' => 'Продукт',
    'filter_state' => 'Состояние',
    'filter_all' => 'Все',
    'action_suspend' => 'Приостановить',
    // curated at 2026-09-23T17:11:09+07:00 — ru curation wave — reactivate vs restore, product domain noun, workspace term
    'action_reactivate' => 'Возобновить',
    'action_approve' => 'Одобрить',
    'action_reject' => 'Отклонить',
    'reason_label' => 'Причина',
    'reason_placeholder' => 'Необязательная причина для этого действия',
    'state_pending' => 'Ожидание',
    'state_active' => 'Активна',
    'state_suspended' => 'Приостановлена',
    'state_expired' => 'Истекла',
    'type_implicit' => 'Неявная',
    'type_explicit' => 'Явная',

    // v2 — per-product insights UI
    // curated at 2026-09-23T17:11:09+07:00 — ru curation wave — reactivate vs restore, product domain noun, workspace term
    'products_title' => 'Домены продуктов',
    // curated at 2026-09-23T17:11:09+07:00 — ru curation wave — reactivate vs restore, product domain noun, workspace term
    'products_subtitle' => 'Аналитика и управление по каждому домену продукта.',
    // curated at 2026-09-23T17:11:09+07:00 — ru curation wave — reactivate vs restore, product domain noun, workspace term
    'products_empty' => 'Домены продуктов не зарегистрированы.',
    'products_list_success' => 'Сводки продуктов получены.',
    'product_show_success' => 'Детали продукта получены.',
    // curated at 2026-09-23T17:11:09+07:00 — ru curation wave — reactivate vs restore, product domain noun, workspace term
    'user_detail_success' => 'Получены сведения о продукте пользователя.',
    'product_title' => 'Продукт: :product',
    // curated at 2026-09-23T17:11:09+07:00 — ru curation wave — reactivate vs restore, product domain noun, workspace term
    'view_product' => 'Смотреть аналитику',
    'back_to_products' => 'Назад к продуктам',
    'fulfillment_target' => 'Цель выдачи',
    'no_activations' => 'Нет активаций для этого продукта.',
    'pagination' => 'Пагинация активаций',

    // Metric labels (keys mirror ProductInsightService output)
    'metric_total_storage_used_bytes' => 'Всего использовано хранилища',
    'metric_total_files' => 'Всего файлов',
    'metric_total_workspaces' => 'Всего рабочих областей',
    'metric_paid_users_count' => 'Пользователи платного уровня',
    'metric_free_users_count' => 'Пользователи бесплатного уровня',
    'metric_grace_users_count' => 'Пользователи льготного уровня',
    'metric_storage_used_bytes' => 'Использовано хранилища',
    'metric_file_count' => 'Файлы',
    'metric_workspace_count_owned' => 'Рабочие области',

    // Tier labels
    'tier' => 'Уровень',
    'tier_free' => 'Бесплатный',
    'tier_paid' => 'Платный',
    'tier_grace' => 'Льготный',
    'tier_n/a' => 'Н/Д',
];
