<?php

namespace App\Services\Notification;

use App\Events\UserActivityNotificationReceived;
use App\Models\ActivityNotification;
use App\Models\User;
use App\Notifications\Activity\NotificationType;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Support\Broadcasting\BroadcastConnection;
use Illuminate\Support\Collection;

/**
 * Business layer for activity notifications.
 *
 * Generic by design: `emit()` takes a NotificationType plus an audience —
 * **roles** (broadcast) and/or **users** (personal) — and persists the record.
 * `broadcastToRoles()` and `sendToUsers()` are thin, readable wrappers. The rule
 * "who can see this" stays in one place. Reads/visibility delegate to the repo.
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    /**
     * Create a notification targeting roles and/or specific users.
     *
     * At least one of `$roles` / `$users` should be non-empty, otherwise the
     * notification is visible to nobody. When `$roles` is null, the type's
     * default roles are used; pass `[]` explicitly for a user-only notification.
     *
     * @param  array<string, mixed>  $data  Message replacements + render context.
     * @param  list<string>|null  $roles  Target role names; null = type defaults.
     * @param  list<string>  $users  Target user ids (personal audience).
     * @param  array<string, mixed>  $actionParams
     */
    public function emit(
        NotificationType $type,
        array $data = [],
        ?array $roles = null,
        array $users = [],
        ?string $actionRoute = null,
        array $actionParams = [],
    ): ActivityNotification {
        $notification = $this->notifications->create(
            [
                'type' => $type->value,
                'data' => $data,
                'action_route' => $actionRoute,
                'action_params' => $actionParams === [] ? null : $actionParams,
            ],
            $roles ?? $type->defaultRoles(),
            $users,
        );

        $this->pushRealtimeToRecipients($notification);

        return $notification;
    }

    /**
     * Broadcast to whole roles (no per-user targeting). Omitting `$roles` uses
     * the type defaults.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $roles
     * @param  array<string, mixed>  $actionParams
     */
    public function broadcastToRoles(
        NotificationType $type,
        array $data = [],
        ?array $roles = null,
        ?string $actionRoute = null,
        array $actionParams = [],
    ): ActivityNotification {
        return $this->emit($type, $data, $roles, [], $actionRoute, $actionParams);
    }

    /**
     * Send a personal notification to specific users only (no role broadcast).
     *
     * @param  list<string>  $users
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $actionParams
     */
    public function sendToUsers(
        NotificationType $type,
        array $users,
        array $data = [],
        ?string $actionRoute = null,
        array $actionParams = [],
    ): ActivityNotification {
        return $this->emit($type, $data, [], $users, $actionRoute, $actionParams);
    }

    /**
     * @return Collection<int, ActivityNotification>
     */
    public function listForViewer(User $viewer, int $limit = 50): Collection
    {
        return $this->notifications->listForViewer($viewer, $limit);
    }

    public function unreadCount(User $viewer): int
    {
        return $this->notifications->unreadCountForViewer($viewer);
    }

    public function isVisibleTo(ActivityNotification $notification, User $viewer): bool
    {
        return $this->notifications->isVisibleTo($notification, $viewer);
    }

    public function markAsRead(ActivityNotification $notification, User $viewer): void
    {
        $this->notifications->markAsRead($notification, $viewer);
    }

    public function markAllAsRead(User $viewer): int
    {
        return $this->notifications->markAllAsRead($viewer);
    }

    /**
     * Fan out a websocket ping to every user who can see the notification.
     */
    private function pushRealtimeToRecipients(ActivityNotification $notification): void
    {
        if (! BroadcastConnection::usesRealtimeNotifications()) {
            return;
        }

        $type = NotificationType::from($notification->type);

        foreach ($this->notifications->recipientUserIds($notification) as $userId) {
            event(new UserActivityNotificationReceived($userId, $type));
        }
    }
}
