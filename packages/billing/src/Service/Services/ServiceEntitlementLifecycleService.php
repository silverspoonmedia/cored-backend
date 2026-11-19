<?php

namespace App\Domain\Service\Services;

use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Support\ServiceEntitlementSnapshot;
use App\Support\Billing\Fulfillment;
use App\Support\Billing\FulfillmentContext;
use App\Support\Notifications\Contracts\NotificationDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Grace + expiry sweeper.
 *
 * The package decides *when* an entitlement moves to Grace/Expired and notifies
 * the owner; what happens to the product afterwards (purge over-quota storage,
 * drop a cached quota) is the target handler's job. Notifications go out by type
 * value through the kernel dispatcher, so this class imports no notification
 * domain type and a host without the bell still sweeps.
 */
class ServiceEntitlementLifecycleService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
    ) {}

    /**
     * @return array{entered_grace: int, expired: int, purged_users: int}
     */
    public function run(): array
    {
        return [
            'entered_grace' => $this->enterGraceForDue(),
            'expired' => $this->expireGraceDue(),
            'purged_users' => 0,
        ];
    }

    public function enterGraceForDue(): int
    {
        $count = 0;

        ServiceEntitlement::query()
            ->where('status', EntitlementStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->with(['user', 'service'])
            ->orderBy('ends_at')
            ->chunkById(50, function ($rows) use (&$count): void {
                foreach ($rows as $entitlement) {
                    /** @var ServiceEntitlement $entitlement */
                    $this->entitlements->enterGrace($entitlement);
                    $this->notifyGrace($entitlement->fresh(['user', 'service']));
                    $count++;
                }
            });

        return $count;
    }

    public function expireGraceDue(): int
    {
        $count = 0;

        ServiceEntitlement::query()
            ->where('status', EntitlementStatus::Grace)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<', now())
            ->with(['user', 'service'])
            ->orderBy('grace_ends_at')
            ->chunkById(50, function ($rows) use (&$count): void {
                foreach ($rows as $entitlement) {
                    /** @var ServiceEntitlement $entitlement */
                    $this->entitlements->markExpired($entitlement);

                    $entitlement = $entitlement->fresh(['user', 'service']);
                    $handler = Fulfillment::handler($entitlement->service?->fulfillment_target);

                    if ($handler === null) {
                        $count++;

                        continue;
                    }

                    $result = $handler->onExpired(ServiceEntitlementSnapshot::fromEntitlement(
                        $entitlement,
                        null,
                        FulfillmentContext::PHASE_EXPIRED,
                    ));

                    if ($result !== []) {
                        Log::info('service.entitlement.expired_purged', [
                            'entitlement_id' => $entitlement->id,
                            'user_id' => $entitlement->user_id,
                            'result' => $result,
                        ]);
                    }

                    $this->notifyExpired($entitlement, $result);
                    $count++;
                }
            });

        return $count;
    }

    private function notifyGrace(ServiceEntitlement $entitlement): void
    {
        if ($entitlement->grace_notified_at !== null) {
            return;
        }

        if (! app()->bound(NotificationDispatcher::class)) {
            $this->markNotified($entitlement);

            return;
        }

        $graceDays = (int) setting_config('service.grace_days', 7);
        $serviceName = $entitlement->service->name ?? 'Service';

        try {
            app(NotificationDispatcher::class)->emit(
                'service_subscription_grace',
                [
                    'service' => $serviceName,
                    'grace_days' => $graceDays,
                    'grace_ends_at' => $entitlement->grace_ends_at?->toDateString() ?? '',
                ],
                users: [(string) $entitlement->user_id],
                roles: [],
                actionRoute: 'services.index',
            );
        } catch (\Throwable $e) {
            Log::warning('service.entitlement.grace_notify_failed', [
                'entitlement_id' => $entitlement->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->markNotified($entitlement);
    }

    /**
     * @param  array<string, mixed>  $result  Product purge report (may be empty).
     */
    private function notifyExpired(ServiceEntitlement $entitlement, array $result): void
    {
        if (! app()->bound(NotificationDispatcher::class)) {
            return;
        }

        $serviceName = $entitlement->service->name ?? 'Service';

        try {
            app(NotificationDispatcher::class)->emit(
                'service_subscription_expired',
                [
                    'service' => $serviceName,
                    'deleted' => (int) ($result['purge']['deleted'] ?? 0),
                ],
                users: [(string) $entitlement->user_id],
                roles: [],
                actionRoute: 'services.index',
            );
        } catch (\Throwable $e) {
            Log::warning('service.entitlement.expire_notify_failed', [
                'entitlement_id' => $entitlement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markNotified(ServiceEntitlement $entitlement): void
    {
        $entitlement->grace_notified_at = now();
        $entitlement->save();
    }
}
