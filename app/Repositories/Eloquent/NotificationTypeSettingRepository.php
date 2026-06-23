<?php

namespace App\Repositories\Eloquent;

use App\Models\NotificationTypeSetting;
use App\Models\Role;
use App\Notifications\Activity\NotificationType;
use App\Repositories\Contracts\NotificationTypeSettingRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NotificationTypeSettingRepository implements NotificationTypeSettingRepositoryInterface
{
    private const CACHE_KEY = 'notification_type_settings.snapshot';

    /**
     * @return Collection<int, NotificationTypeSetting>
     */
    public function listAll(): Collection
    {
        return $this->snapshot()->values();
    }

    public function findByType(string $type): ?NotificationTypeSetting
    {
        return $this->snapshot()->get($type);
    }

    public function read(string $type): NotificationTypeSetting
    {
        $setting = $this->findByType($type);

        if ($setting === null) {
            abort(404);
        }

        return $setting;
    }

    public function syncRoles(NotificationTypeSetting $setting, array $idsOrNames): void
    {
        if ($idsOrNames === []) {
            $setting->roles()->sync([]);
            $this->flushCache();

            return;
        }

        $roleIds = Role::query()
            ->where('guard_name', 'api')
            ->where(function (Builder $q) use ($idsOrNames): void {
                $q->whereIn('id', $idsOrNames)
                    ->orWhereIn('name', $idsOrNames);
            })
            ->pluck('id')
            ->all();

        $setting->roles()->sync($roleIds);
        $this->flushCache();
    }

    public function syncUsers(NotificationTypeSetting $setting, array $userIds): void
    {
        $setting->targetUsers()->sync(array_values(array_unique($userIds)));
        $this->flushCache();
    }

    public function resolveAudience(
        NotificationType $type,
        array $data = [],
        ?array $overrideRoles = null,
        array $overrideUsers = [],
    ): array {
        $setting = $this->findByType($type->value);

        if ($overrideRoles !== null) {
            $roles = $overrideRoles;
        } elseif ($setting !== null) {
            $roles = $setting->roles->pluck('name')->values()->all();
        } else {
            $roles = $type->defaultRoles();
        }

        $users = array_values(array_unique($overrideUsers));

        if ($setting !== null) {
            $users = array_values(array_unique([
                ...$users,
                ...$setting->targetUsers->pluck('id')->all(),
            ]));

            if ($setting->notify_subject_user) {
                $subjectId = $data['subject_user_id'] ?? null;

                if (is_string($subjectId) && $subjectId !== '') {
                    $users[] = $subjectId;
                }
            }
        }

        return [
            'roles' => $roles,
            'users' => array_values(array_unique($users)),
        ];
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Collection<string, NotificationTypeSetting>
     */
    private function snapshot(): Collection
    {
        /** @var Collection<string, NotificationTypeSetting> $settings */
        $settings = Cache::rememberForever(self::CACHE_KEY, function (): Collection {
            return NotificationTypeSetting::query()
                ->with(['roles', 'targetUsers'])
                ->get()
                ->keyBy('type');
        });

        return $settings;
    }
}
