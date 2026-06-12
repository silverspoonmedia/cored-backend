<?php

namespace App\Notifications\Activity;

/**
 * Catalogue of activity-notification types.
 *
 * Each type maps to a localized message (lang/{locale}/api/notifications.php
 * under `types.{type}`), a Bootstrap icon, and the default target roles that
 * should receive it. Adding a new action = add a case here + a lang entry +
 * fire NotificationEvent from wherever the action happens. No engine change.
 */
enum NotificationType: string
{
    case UserCreated = 'user_created';

    /**
     * Bootstrap icon class rendered by the web bell.
     */
    public function icon(): string
    {
        return match ($this) {
            self::UserCreated => 'bi bi-person-plus',
        };
    }

    /**
     * Default target roles (names) when an emitter does not override them.
     *
     * @return list<string>
     */
    public function defaultRoles(): array
    {
        return match ($this) {
            self::UserCreated => ['Root', 'Admin'],
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
