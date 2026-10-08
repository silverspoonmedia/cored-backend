<?php

use App\Domain\Archivd\Services\WorkspaceAccessService;
use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Support\InvoiceChannelAuthorizer;
use App\Domain\Service\Models\ServicePurchase;
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

Broadcast::channel('payments.invoice.{invoiceId}', function (User $user, string $invoiceId): bool {
    try {
        $invoice = Invoice::query()->find($invoiceId);

        if ($invoice === null) {
            return false;
        }

        return app(InvoiceChannelAuthorizer::class)->canSubscribe($user, $invoice);
    } catch (Throwable) {
        return false;
    }
});

Broadcast::channel('payments.routing', function (User $user): bool {
    return $user->can('initiate payment') || $user->can('view invoice');
});

Broadcast::channel('navigation.menus', function (User $user): bool {
    return $user->can('view menu');
});

Broadcast::channel('services.catalog', function (User $user): bool {
    return $user->can('view service');
});

Broadcast::channel('services.purchase.{purchaseId}', function (User $user, string $purchaseId): bool {
    return ServicePurchase::query()
        ->whereKey($purchaseId)
        ->where('user_id', $user->id)
        ->exists();
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

Broadcast::channel('payments.admin', function (User $user): bool {
    return $user->can('view invoice');
});
