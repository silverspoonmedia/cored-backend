<?php

namespace App\Notifications\Activity;

/**
 * Catalogue of activity-notification types.
 *
 * Each type maps to a localized message (lang/{locale}/api/notifications.php
 * under `types.{type}`), a Bootstrap icon, and optional enum fallbacks when no
 * database setting exists. **Audience targeting** (roles + users) is stored in
 * `notification_type_settings` and managed via the admin UI — see
 * docs/notifications.md.
 *
 * Adding a new action = add a case here + a lang entry + fire
 * ActivityNotificationEvent from wherever the action happens. No engine change.
 */
enum NotificationType: string
{
    // Broadcast: a new user was created (seen by Root + Admin).
    case UserCreated = 'user_created';

    // Personal: a user's own account was updated (seen by that user only).
    case AccountUpdated = 'account_updated';

    /**
     * Bootstrap icon class rendered by the web bell.
     */
    public function icon(): string
    {
        return match ($this) {
            self::UserCreated => 'bi bi-person-plus',
            self::AccountUpdated => 'bi bi-person-gear',
        };
    }

    /**
     * Fallback target roles when no database setting exists (e.g. before seeding).
     *
     * @return list<string>
     */
    public function defaultRoles(): array
    {
        return match ($this) {
            self::UserCreated => ['Root', 'Admin'],
            self::AccountUpdated => [],
        };
    }

    /**
     * Translation key for the human-readable message.
     */
    public function messageKey(): string
    {
        return "api/notifications.types.{$this->value}";
    }

    /**
     * DataTable ID that should reload when this notification fires. Null for
     * types that don't correspond to a list screen (e.g. personal updates).
     */
    public function tableId(): ?string
    {
        return match ($this) {
            self::UserCreated => 'users-table',
            self::AccountUpdated => null,
        };
    }
}
