<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Events\RolePermissionChanged;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire RBAC realtime events after role/permission mutations.
 */
class RbacRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function changed(string $action, ?string $roleId = null, ?string $userId = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new RolePermissionChanged($action, $roleId, $userId));
    }
}
