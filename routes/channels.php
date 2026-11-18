<?php

use App\Domain\Archivd\Services\WorkspaceAccessService;
use App\Domain\Identity\Models\User;
use App\Support\Broadcasting\ChannelRegistrarRegistry;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    return $user->id === $id;
});

Broadcast::channel('archivd.user.{userId}', function (User $user, string $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('archivd.workspace.{workspaceId}', function (User $user, string $workspaceId): bool {
    try {
        $access = app(WorkspaceAccessService::class);
        $workspace = $access->findOrFail($workspaceId);

        return $access->canView($workspace, $user);
    } catch (Throwable) {
        return false;
    }
});

Broadcast::channel('navigation.menus', function (User $user): bool {
    return $user->can('view menu');
});

Broadcast::channel('inference.catalog', function (User $user): bool {
    return $user->can('view inference catalog');
});

Broadcast::channel('ai.connections', function (User $user): bool {
    return $user->can('view ai connections');
});

Broadcast::channel('settings.{group}', function (User $user, string $group): bool {
    return $user->can('view settings');
});

Broadcast::channel('settings.currencies', function (User $user): bool {
    return $user->can('view settings');
});

Broadcast::channel('logs.errors', function (User $user): bool {
    return $user->can('view error log');
});

Broadcast::channel('logs.activity', function (User $user): bool {
    return $user->can('view activity log');
});

Broadcast::channel('product-activations', function (User $user): bool {
    return $user->can('view product activations');
});

Broadcast::channel('domain-access', function (User $user): bool {
    return $user->can('view domain access');
});

Broadcast::channel('rbac.roles', function (User $user): bool {
    return $user->can('view user');
});

Broadcast::channel('rbac.users', function (User $user): bool {
    return $user->can('view user');
});

// Legacy aggregated RBAC channel — kept so older SPA builds keep receiving
// role-permission pings until they adopt rbac.roles / rbac.users.
Broadcast::channel('rbac', function (User $user): bool {
    return $user->can('view user');
});

/*
|--------------------------------------------------------------------------
| Packaged domains
|--------------------------------------------------------------------------
| A packaged core domain answers for its own channels (`payments.*`,
| `services.*`) through this kernel registry, so this file never imports a
| package model just to authorize a socket. Unregistered = no channels.
*/
foreach (app(ChannelRegistrarRegistry::class)->all() as $registrar) {
    $registrar->register();
}
