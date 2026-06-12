<?php

namespace App\Services\Notification;

use App\Models\ActivityNotification;
use App\Models\User;
use App\Notifications\Activity\NotificationType;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Business layer for activity notifications.
 *
 * Generic by design: `emit()` takes a NotificationType plus context and fans
 * the record out to whole roles. Listeners (or any caller) use it so the rule
 * "who can see this" stays in one place. Reads/visibility delegate to the repo.
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    /**
     * Create a role-targeted notification.
     *
     * @param  array<string, mixed>  $data  Message replacements + render context.
     * @param  list<string>|null  $roles  Target role names; null = type defaults.
     * @param  array<string, mixed>  $actionParams
     */
    public function emit(
        NotificationType $type,
        array $data = [],
        ?array $roles = null,
        ?string $actionRoute = null,
        array $actionParams = [],
    ): ActivityNotification {
        return $this->notifications->create(
            [
                'type' => $type->value,
                'data' => $data,
                'action_route' => $actionRoute,
                'action_params' => $actionParams === [] ? null : $actionParams,
            ],
            $roles ?? $type->defaultRoles(),
        );
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
}
