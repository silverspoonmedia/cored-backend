<?php

namespace App\Repositories\Contracts;

use App\Models\ActivityNotification;
use App\Models\User;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    /**
     * Persist a notification and attach its target roles (by id or name).
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $roleIdsOrNames
     */
    public function create(array $attributes, array $roleIdsOrNames): ActivityNotification;

    /**
     * Notifications visible to the viewer (they hold at least one target role),
     * newest first. Each model carries a `is_read` boolean for the viewer.
     *
     * @return Collection<int, ActivityNotification>
     */
    public function listForViewer(User $viewer, int $limit = 50): Collection;

    /**
     * Count notifications the viewer has not yet read.
     */
    public function unreadCountForViewer(User $viewer): int;

    /**
     * Whether the viewer is allowed to see this notification (role overlap).
     */
    public function isVisibleTo(ActivityNotification $notification, User $viewer): bool;

    /**
     * Mark a single notification read for the viewer (idempotent).
     */
    public function markAsRead(ActivityNotification $notification, User $viewer): void;

    /**
     * Mark every notification visible to the viewer as read. Returns the count
     * of notifications newly marked.
     */
    public function markAllAsRead(User $viewer): int;
}
