<?php

namespace App\Repositories\Contracts;

use App\Models\NotificationTypeSetting;
use App\Notifications\Activity\NotificationType;
use Illuminate\Support\Collection;

interface NotificationTypeSettingRepositoryInterface
{
    /**
     * @return Collection<int, NotificationTypeSetting>
     */
    public function listAll(): Collection;

    public function findByType(string $type): ?NotificationTypeSetting;

    public function read(string $type): NotificationTypeSetting;

    /**
     * @param  list<string>  $idsOrNames
     */
    public function syncRoles(NotificationTypeSetting $setting, array $idsOrNames): void;

    /**
     * @param  list<string>  $userIds
     */
    public function syncUsers(NotificationTypeSetting $setting, array $userIds): void;

    /**
     * Resolve the broadcast roles and personal user ids for a notification emit.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $overrideRoles
     * @param  list<string>  $overrideUsers
     * @return array{roles: list<string>, users: list<string>}
     */
    public function resolveAudience(
        NotificationType $type,
        array $data = [],
        ?array $overrideRoles = null,
        array $overrideUsers = [],
    ): array;

    public function flushCache(): void;
}
