<?php

namespace App\Domain\Service\Services;

use App\Domain\Notification\Notifications\NotificationType;
use App\Domain\Notification\Services\NotificationService;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\FileHosting\Services\EnforceFreeTierQuotaService;
use Illuminate\Support\Facades\Log;

class ServiceEntitlementLifecycleService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
        private readonly ServiceFulfillmentService $fulfillment,
        private readonly EnforceFreeTierQuotaService $purge,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @return array{entered_grace: int, expired: int, purged_users: int}
     */
    public function run(): array
    {
        return [
            'entered_grace' => $this->enterGraceForDue(),
            'expired' => $this->expireGraceDue(),
            'purged_users' => 0, // counted inside expire
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
                    $this->fulfillment->revokeFileHostingUnlimited($entitlement->user_id);

                    if ($entitlement->service?->fulfillment_target === 'file_hosting') {
                        $result = $this->purge->enforce($entitlement->user_id);
                        Log::info('service.entitlement.expired_purged', [
                            'entitlement_id' => $entitlement->id,
                            'user_id' => $entitlement->user_id,
                            'purge' => $result,
                        ]);
                        $this->notifyExpired($entitlement->fresh(['user', 'service']), $result);
                    }

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

        $userId = $entitlement->user_id;
        $graceDays = (int) config('service.grace_days', 7);
        $serviceName = $entitlement->service?->name ?? 'Service';

        try {
            $this->notifications->emit(
                NotificationType::ServiceSubscriptionGrace,
                [
                    'service' => $serviceName,
                    'grace_days' => $graceDays,
                    'grace_ends_at' => $entitlement->grace_ends_at?->toDateString() ?? '',
                ],
                roles: [],
                users: [$userId],
                actionRoute: 'services.index',
            );
        } catch (\Throwable $e) {
            Log::warning('service.entitlement.grace_notify_failed', [
                'entitlement_id' => $entitlement->id,
                'error' => $e->getMessage(),
            ]);
        }

        $entitlement->grace_notified_at = now();
        $entitlement->save();
    }

    /**
     * @param  array{deleted: int, freed_bytes: int, retained_bytes: int, limit_bytes: int}  $purge
     */
    private function notifyExpired(ServiceEntitlement $entitlement, array $purge): void
    {
        $userId = $entitlement->user_id;
        $serviceName = $entitlement->service?->name ?? 'Service';

        try {
            $this->notifications->emit(
                NotificationType::ServiceSubscriptionExpired,
                [
                    'service' => $serviceName,
                    'deleted' => $purge['deleted'],
                ],
                roles: [],
                users: [$userId],
                actionRoute: 'services.index',
            );
        } catch (\Throwable $e) {
            Log::warning('service.entitlement.expire_notify_failed', [
                'entitlement_id' => $entitlement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
