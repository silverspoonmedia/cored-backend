<?php

namespace App\Notifications\Activity;

/**
 * Catalogue of activity-notification types.
 *
 * Each type maps to a localized message (lang/{locale}/api/notifications.php
 * under `types.{type}`), a Bootstrap icon, and the default target roles for the
 * **broadcast** audience. Per-user (personal) notifications pass an explicit
 * user list instead and usually have empty `defaultRoles()`.
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
     * Default target roles (names) for the broadcast audience when an emitter
     * does not override them. Personal-only types return an empty list.
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
}
