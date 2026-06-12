<?php

namespace App\Repositories\Eloquent;

use App\Models\ActivityNotification;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function create(array $attributes, array $roleIdsOrNames): ActivityNotification
    {
        return DB::transaction(function () use ($attributes, $roleIdsOrNames): ActivityNotification {
            $notification = ActivityNotification::query()->create($attributes);

            $notification->roles()->sync($this->resolveRoleIds($roleIdsOrNames));

            return $notification->load('roles');
        });
    }

    public function listForViewer(User $viewer, int $limit = 50): Collection
    {
        $roleIds = $this->viewerRoleIds($viewer);

        if ($roleIds === []) {
            return new Collection;
        }

        return ActivityNotification::query()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('roles.id', $roleIds))
            ->withCount(['readers as is_read' => fn (Builder $q) => $q->where('user_id', $viewer->id)])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->each(fn (ActivityNotification $n) => $n->setAttribute('is_read', (bool) $n->getAttribute('is_read')));
    }

    public function unreadCountForViewer(User $viewer): int
    {
        $roleIds = $this->viewerRoleIds($viewer);

        if ($roleIds === []) {
            return 0;
        }

        return ActivityNotification::query()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('roles.id', $roleIds))
            ->whereDoesntHave('readers', fn (Builder $q) => $q->where('user_id', $viewer->id))
            ->count();
    }

    public function isVisibleTo(ActivityNotification $notification, User $viewer): bool
    {
        $roleIds = $this->viewerRoleIds($viewer);

        if ($roleIds === []) {
            return false;
        }

        return $notification->roles()->whereIn('roles.id', $roleIds)->exists();
    }

    public function markAsRead(ActivityNotification $notification, User $viewer): void
    {
        $notification->readers()->syncWithoutDetaching([
            $viewer->id => ['read_at' => now()],
        ]);
    }

    public function markAllAsRead(User $viewer): int
    {
        $roleIds = $this->viewerRoleIds($viewer);

        if ($roleIds === []) {
            return 0;
        }

        $unread = ActivityNotification::query()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('roles.id', $roleIds))
            ->whereDoesntHave('readers', fn (Builder $q) => $q->where('user_id', $viewer->id))
            ->pluck('id');

        if ($unread->isEmpty()) {
            return 0;
        }

        $now = now();

        // Insert pivot rows directly. We avoid a User relation method here because
        // Laravel's Notifiable trait already owns `notifications()` / `readNotifications()`.
        DB::table('activity_notification_reads')->insert(
            $unread->map(fn (string $id): array => [
                'notification_id' => $id,
                'user_id' => $viewer->id,
                'read_at' => $now,
            ])->all(),
        );

        return $unread->count();
    }

    /**
     * Resolve a mixed list of role ids or names to concrete role ids (api guard).
     *
     * @param  list<string>  $idsOrNames
     * @return list<string>
     */
    private function resolveRoleIds(array $idsOrNames): array
    {
        if ($idsOrNames === []) {
            return [];
        }

        return Role::query()
            ->where('guard_name', 'api')
            ->where(function (Builder $q) use ($idsOrNames): void {
                $q->whereIn('id', $idsOrNames)
                    ->orWhereIn('name', $idsOrNames);
            })
            ->pluck('id')
            ->all();
    }

    /**
     * The viewer's role ids on the api guard.
     *
     * @return list<string>
     */
    private function viewerRoleIds(User $viewer): array
    {
        return $viewer->roles()->pluck('id')->all();
    }
}
