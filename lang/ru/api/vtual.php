<?php

return [
    'creator' => [
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'list_success' => 'Авторы загружены.',
        'owned_list_success' => 'Ваши профили vTuber загружены.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'show_success' => 'Автор загружен.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'created' => 'Автор создан.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'orphan_created' => 'Автор-сирота создан (без владельца; доступен для заявки).',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'updated' => 'Автор обновлён.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'archived' => 'Автор архивирован.',
        'name_required' => 'Укажите отображаемое имя.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'slug_taken' => 'Этот слаг автора уже занят.',
        'not_found' => 'Профиль автора не найден.',
        'self_create_disabled' => 'Прямое создание профиля отключено. Подайте заявку на существующий профиль или попросите сотрудников создать его.',
    ],
    'orphan' => [
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'list_success' => 'Авторы-сироты загружены.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'show_success' => 'Автор-сирота загружен.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'not_claimable' => 'Этот профиль больше не сирота (уже есть заявка или профиль не активен).',
    ],
    'vtuber' => [
        'show_success' => 'Профиль vTuber загружен.',
        'not_found' => 'Профиль vTuber не найден или недоступен.',
    ],
    'public' => [
        'directory_success' => 'Публичный каталог vTuber загружен.',
        'show_success' => 'Публичный профиль vTuber загружен.',
        'contents_success' => 'Публичная лента контента загружена.',
        'landing_success' => 'Данные публичной главной страницы загружены.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'management_show_success' => 'Публичный профиль организации загружен.',
    ],
    'merge' => [
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'success' => 'Профили объединены. Источник архивирован; данные перенесены в целевой профиль.',
        'self' => 'Нельзя объединить профиль с самим собой.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'owner_required' => 'Для объединения вы должны быть владельцем обоих профилей — источника и назначения.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'destination_archived' => 'Целевой профиль архивирован и не может принять объединение.',
        'source_archived' => 'Исходный профиль уже архивирован.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'channel_clash' => 'У обоих профилей один и тот же канал платформы (:external_id). Сначала архивируйте или удалите один из каналов.',
    ],
    'claim' => [
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'claimable_success' => 'Профили, доступные для заявки, загружены.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'list_success' => 'Ваши заявки загружены.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'show_success' => 'Заявка загружена.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'started' => 'Заявка создана. Разместите токен в разделе «О канале» или в описании канала, затем запустите проверку.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'verified' => 'Заявка проверена. Теперь вы владелец.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'verify_queued' => 'Проверка поставлена в очередь. Она выполнится, когда пакет будет готов (до 50 ожидающих или после окна ожидания).',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'verify_already_queued' => 'Проверка для этой заявки уже в очереди.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'blocked' => 'Сейчас вы не можете подать заявку на этот профиль. Повторите после окончания периода блокировки.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'cancelled' => 'Заявка отменена.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'accepted_anyway' => 'Заявка принята без проверки описания (сотрудники).',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'not_claimable' => 'На этого автора нельзя подать заявку (уже есть владелец или профиль не активен).',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'channel_mismatch' => 'Канал подтверждения должен принадлежать автору, на которого подаётся заявка.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'token_not_found' => 'Токен заявки не найден в описании канала. Добавьте токен и повторите попытку.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'not_pending' => 'Эта заявка не ожидает проверки.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'already_owned' => 'У этого автора уже есть владелец. Заявка не может быть завершена.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'expired' => 'Срок этой заявки истёк. Создайте новую заявку.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'max_attempts' => 'Слишком много попыток проверки для этой заявки. Создайте новую заявку или дождитесь помощи сотрудников.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'cooldown' => 'Подождите :seconds секунд перед следующей проверкой.',
        'accept_anyway_disabled' => 'Принятие без проверки отключено в этой среде.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'discord_verify_disabled' => 'Проверка заявок через Discord отключена в этой среде.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'discord_not_connected' => 'Подключите Discord в разделе «Связанные аккаунты» перед использованием проверки через Discord.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'discord_connections_failed' => 'Не удалось загрузить связанные аккаунты Discord. Попробуйте переподключить Discord.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'discord_no_match' => 'В вашем профиле Discord не найдено подходящего подтверждённого аккаунта YouTube или Twitch.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'discord_verify_unsupported_platform' => 'Проверка через Discord недоступна для этой платформы.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'channel_missing' => 'Канал подтверждения заявки отсутствует.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'max_pending' => 'У вас уже :max ожидающих заявок. Отмените одну или сначала завершите проверку.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'pending_success' => 'Ожидающие заявки загружены.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'superseded' => 'Другая заявка на этого автора уже завершена. Эта заявка закрыта.',
    ],
    'management' => [
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'list_success' => 'Организации загружены.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'show_success' => 'Организация загружена.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'created' => 'Организация создана.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'updated' => 'Организация обновлена.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'deleted' => 'Организация архивирована.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'name_required' => 'Укажите название организации.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'slug_taken' => 'Этот слаг организации уже занят.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'creator_assigned' => 'Автор назначен в организацию.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'creator_unassigned' => 'Автор откреплён от организации.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'assignment_not_found' => 'Активное назначение в организацию не найдено.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'not_found' => 'Профиль организации не найден или недоступен.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'members_list_success' => 'Участники организации загружены.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'member_added' => 'Участник добавлен в организацию.',
        'member_updated' => 'Роль участника обновлена.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'member_already_exists' => 'Этот пользователь уже является участником организации.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'member_not_found' => 'Участник организации не найден.',
        'role_invalid' => 'Недопустимая роль участника или пользователь.',
        'cannot_edit_self' => 'Нельзя изменить или удалить собственное членство.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'last_owner' => 'В организации должен оставаться хотя бы один владелец.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'invites_list_success' => 'Приглашения организации загружены.',
        'invite_sent' => 'Приглашение отправлено.',
        'invite_cancelled' => 'Приглашение отменено.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'invite_accepted' => 'Приглашение принято. Добро пожаловать в организацию.',
        'invite_declined' => 'Приглашение отклонено.',
        'invite_already_pending' => 'Для этого адреса уже есть ожидающее приглашение.',
        'invite_not_actionable' => 'Это приглашение больше нельзя изменить.',
        'invite_expired' => 'Срок действия приглашения истёк или оно недействительно.',
        'invite_wrong_account' => 'Это приглашение предназначено другому аккаунту.',
        'invite_user_not_found' => 'Нет зарегистрированного пользователя с таким адресом электронной почты.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'owner_invite_not_claimable' => 'У этой организации уже есть владелец. Приглашение владельца доступно только до передачи.',
        'creator_invite_mode_invalid' => 'Режим приглашения автора должен быть "existing" или "new".',
        'creator_invite_not_owner' => 'Приглашённый аккаунт не является владельцем выбранного профиля vTuber.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'creator_already_linked' => 'Этот профиль vTuber уже привязан к этой организации.',
        'creator_invite_name_required' => 'Требуется отображаемое имя для нового профиля vTuber.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'events_list_success' => 'События организации загружены.',
        'event_created' => 'Событие создано.',
        // curated at 2026-09-24T18:10:00+07:00 — ru curation wave: vTual management event edit
        'event_updated' => 'Событие обновлено.',
        'event_cancelled' => 'Событие отменено.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'event_not_found' => 'Событие для этой организации не найдено.',
        'event_not_actionable' => 'Это событие больше нельзя изменить.',
    ],
    'mail' => [
        'invite_subject' => 'Приглашение в :management',
        'creator_invite_subject' => 'Приглашение профиля vTuber от :management',
        'creator_invite_line' => 'Вас пригласили привязать ваш профиль vTuber к :management.',
        'invite_greeting' => 'Здравствуйте!',
        'invite_line' => 'Вас пригласили присоединиться к :management в роли :role.',
        'invite_action' => 'Просмотреть приглашение',
        'invite_expiry' => 'Срок действия приглашения истекает :date.',
    ],
    'channel' => [
        'list_success' => 'Каналы загружены.',
        'show_success' => 'Канал загружен.',
        'created' => 'Канал зарегистрирован и подтверждён.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'seeded' => 'Канал распознан и зарегистрирован (добавлен сотрудниками; без заявки по описанию канала).',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'registered_pending' => 'Канал поставлен в очередь на пакетное распознавание (без немедленного вызова API).',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'refreshed' => 'Профиль канала обновлён и подтверждён платформой.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'refresh_queued' => 'Обновление канала поставлено в очередь (вместе с другими ожидающими запросами).',
        'archived' => 'Канал архивирован.',
        'deleted' => 'Ожидающий канал удалён.',
        'pending_not_archivable' => 'Ожидающие каналы нельзя архивировать. Удалите их.',
        'not_pending_delete' => 'Так можно удалить только ожидающие каналы.',
        'restored' => 'Канал восстановлен.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'promoted' => 'Канал опубликован; загрузка профиля и контента поставлена в очередь.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'not_private_discovered' => 'Опубликовать можно только найденные приватные каналы.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'promote_not_discovered' => 'Этим действием можно опубликовать только каналы, найденные через Discord.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'promote_unsupported_platform' => 'Публикация не поддерживается для этой платформы.',
        'not_archived' => 'Канал не архивирован.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'lifecycle_cooldown' => 'Подождите :seconds секунд перед повторной архивацией или восстановлением этого канала.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'lifecycle_rate_limited' => 'Слишком много действий архивации/восстановления каналов. Повторите позже.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'archived_list_success' => 'Архивированные каналы загружены.',
        'platform_unknown' => 'Неизвестная платформа.',
        'platform_disabled' => 'Эта платформа пока не включена.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'external_id_required' => 'Укажите внешний ID канала.',
        'external_id_taken' => 'Канал с таким ID платформы уже существует.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'duplicate_on_creator' => 'Этот канал платформы уже зарегистрирован в данном профиле vTuber.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'profile_required' => 'Укажите профиль канала (ID, хендл, логин или URL).',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'profile_invalid' => 'Не удалось распознать профиль канала. Используйте ID платформы, @хендл/логин или URL профиля.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'profile_platform_mismatch' => 'Этот URL профиля принадлежит другой платформе. Смените платформу в списке или вставьте подходящий профиль.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'profile_not_found' => 'Канал для этого профиля на платформе не найден.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'profile_unsupported_platform' => 'Распознавание профиля не поддерживается для этой платформы.',
    ],
    'channel_claim' => [
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'list_success' => 'Заявки на каналы загружены.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'show_success' => 'Заявка на канал загружена.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'started' => 'Заявка на канал создана. Разместите токен в разделе «О канале» или в описании канала, затем запустите проверку.',
        'verified' => 'Канал подтверждён и привязан к этому профилю.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'cancelled' => 'Заявка на канал отменена.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'accepted_anyway' => 'Канал принят без проверки описания (сотрудники).',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'token_not_found' => 'Токен заявки не найден в описании канала. Добавьте токен и повторите попытку.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'not_pending' => 'Эта заявка на канал не ожидает проверки.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'expired' => 'Срок этой заявки на канал истёк. Создайте новую заявку.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'max_attempts' => 'Слишком много попыток проверки для этой заявки на канал. Создайте новую заявку или дождитесь помощи сотрудников.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'cooldown' => 'Подождите :seconds секунд перед следующей проверкой.',
        'accept_anyway_disabled' => 'Принятие без проверки отключено в этой среде.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'pending_other' => 'У другого пользователя уже есть ожидающая заявка на этот канал платформы.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'discord_verify_disabled' => 'Проверка заявок на каналы через Discord отключена.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'discord_verify_unsupported_platform' => 'Проверка через Discord не поддерживается для этой платформы.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'discord_not_connected' => 'Подключите Discord в разделе «Профиль → Связанные аккаунты» перед проверкой.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'discord_connections_failed' => 'Не удалось загрузить подключения Discord. Повторите попытку или используйте проверку токеном в описании канала.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'discord_no_match' => 'Ни одно подтверждённое подключение Discord не соответствует этому каналу платформы.',
        'discord_verified' => 'Канал подтверждён через Discord и привязан к этому профилю.',
    ],
    'link' => [
        'list_success' => 'Внешние ссылки загружены.',
        'created' => 'Внешняя ссылка добавлена.',
        'updated' => 'Внешняя ссылка обновлена.',
        'deleted' => 'Внешняя ссылка удалена.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'url_required' => 'Укажите URL ссылки.',
        'not_creator' => 'Эта ссылка не принадлежит профилю vTuber.',
    ],
    'content' => [
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'external_id_required' => 'Укажите ID контента платформы.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'appearance_primary_via_set' => 'Основной канал-ведущий нельзя добавить как участие. Используйте setPrimary.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'appearance_role_invalid' => 'Недопустимая роль участия в контенте.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'appearance_platform_mismatch' => 'Канал участия должен быть на той же платформе, что и контент.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'appearance_already_primary' => 'Этот канал уже является основным ведущим контента.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'appearance_already_attached' => 'Этот канал уже привязан как участие в контенте.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'appearance_not_found' => 'Участие в контенте для этого канала не найдено.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'cannot_detach_primary' => 'Нельзя открепить основной канал-ведущий. Сначала назначьте другой основной.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'fetch_platform_unsupported' => 'Загрузка контента не поддерживается для платформы :platform.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'fetch_channel_not_active' => 'Для загрузки контента требуется активный канал.',
        'fetch_video_channel_mismatch' => 'Видео :video не принадлежит каналу :channel.',
    ],
    'platform' => [
        'list_success' => 'Платформы загружены.',
    ],
    'credential' => [
        'list_success' => 'Учётные данные платформ загружены.',
        'show_success' => 'Учётные данные платформы загружены.',
        'created' => 'Учётные данные платформы созданы.',
        'updated' => 'Учётные данные платформы обновлены.',
        'deleted' => 'Учётные данные платформы удалены.',
        'revoked' => 'Учётные данные платформы отозваны.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'label_required' => 'Укажите метку учётных данных.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'label_taken' => 'Эта метка учётных данных уже используется для платформы.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'payload_invalid' => 'Недопустимые секретные данные учётных данных.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'payload_missing' => 'Укажите секретное поле :field для этого типа учётных данных.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'payload_unknown_key' => 'Секретное поле :field не допускается для этого типа учётных данных.',
        'test_success' => 'Проверка учётных данных успешна; учётные данные активны.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'test_missing_secret' => 'Секрет учётных данных неполон для этой проверки.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'test_unsupported' => 'Проверка недоступна для этой платформы и типа учётных данных.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'test_not_allowed_status' => 'Отозванные учётные данные нельзя проверить.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'test_youtube_ok' => 'Ключ API YouTube принят.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'test_youtube_empty' => 'API YouTube не вернул каналов для ID проверки.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'test_twitch_ok' => 'Токен приложения Twitch выпущен, проверка Helix успешна.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'test_bearer_ok' => 'Bearer-токен принят проверкой платформы.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'test_bearer_ok_no_probe' => 'Bearer-токен найден; проверка платформы пропущена.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'status_active_requires_test' => 'Используйте действие «Проверить», чтобы отметить учётные данные активными.',
        'revoke_requires_active' => 'Отозвать можно только активные учётные данные.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'delete_requires_revoke' => 'Отзовите эти учётные данные перед удалением.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'not_active' => 'Учётные данные не активны.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'app_token_oauth_only' => 'Токены доступа приложения применимы только к учётным данным oauth_app.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'app_token_twitch_only' => 'Выпуск токена доступа приложения реализован только для Twitch.',
    ],
    'policy' => [
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'denied' => 'У вас нет разрешения выполнить это действие.',
        'access_denied' => 'У вас нет доступа к этому ресурсу vTual.',
        'mutate_denied' => 'Вы не можете изменять этот ресурс vTual.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'archive_denied' => 'Архивировать эту организацию может только её владелец или участник-администратор.',
        'members_denied' => 'Вы не можете управлять участниками этого ресурса.',
        // curated at 2026-09-23T19:58:16+07:00 — ru curation wave: management entity settled on организация
        'assign_denied' => 'Вы не можете назначать авторов для этой организации.',
    ],
    'integrations' => [
        'unsupported_operation' => 'Платформа :platform не поддерживает операцию :operation.',
        'platform_disabled' => 'Платформа :platform отключена или недоступна.',
        'platform_unknown' => 'Неизвестная платформа :platform.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'no_active_credential' => 'Нет активных учётных данных для :platform. Сначала создайте и проверьте их.',
        'credential_required' => 'У клиента платформы нет привязанных учётных данных.',
        'missing_api_key' => 'В учётных данных YouTube отсутствует api_key.',
        'missing_client_id' => 'В учётных данных Twitch отсутствует client_id.',
        'ids_required' => 'Требуется хотя бы один :label.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'batch_too_large' => 'Слишком много :label (:count). Максимум на запрос: :max.',
        'batch_max_invalid' => 'Недопустимая настройка максимального размера пакета.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'invalid_part' => 'Часть YouTube :resource «:part» не допускается.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'parts_required' => 'Укажите хотя бы одну часть ресурса YouTube :resource.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'handle_required' => 'Укажите хендл или логин канала.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'youtube_channel_id_required' => 'Укажите действительный ID канала YouTube (UC…).',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'search_event_type_invalid' => 'Тип события поиска должен быть live, upcoming или completed.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'feed_fetch_failed' => 'Не удалось запросить ленту YouTube (HTTP :status).',
        'feed_parse_failed' => 'Не удалось разобрать ответ ленты YouTube.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'upstream_error' => 'Запрос к платформе :platform завершился ошибкой (HTTP :status).',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'upstream_error_detail' => 'Запрос к платформе :platform завершился ошибкой (HTTP :status): :detail',
        'upstream_connection_error' => 'Не удалось подключиться к платформе :platform.',
        // curated at 2026-09-25T21:30:00+07:00 — upstream retry/quota ops log lines
        'upstream_retry_scheduled' => 'Запрос к платформе :platform не удался (HTTP :status); повторная попытка :attempt через :delay мс.',
        'upstream_quota_exhausted' => 'Квота платформы :platform исчерпана (HTTP 429). Повторите попытку после сброса окна квоты.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'probe_success' => 'Проверка API платформы завершена.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'probe_failed' => 'Проверка API платформы неожиданно завершилась ошибкой.',
        'probe_ids_required' => 'Для этого метода требуется хотя бы один ID.',
        // curated at 2026-09-23T19:26:42+07:00 — ru curation wave: settled terms
        'probe_unknown_method' => 'Неизвестный метод проверки: :method',
    ],
];
