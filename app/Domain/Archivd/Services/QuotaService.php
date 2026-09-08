<?php

namespace App\Domain\Archivd\Services;

use App\Domain\Archivd\Exceptions\ArchivdException;
use App\Domain\Archivd\Models\FileUpload;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\FileVariant;
use App\Domain\Archivd\Models\UserQuota;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Services\ServiceEntitlementService;
use App\Domain\Service\Support\ArchivdCatalog;
use App\Support\Presentation\BytePresenter;
use App\Support\Product\Models\ProductActivation;
use Illuminate\Support\Collection;

class QuotaService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
    ) {}

    /**
     * @return array{
     *     used_bytes: int,
     *     used_bytes_human: string|null,
     *     pending_bytes: int,
     *     pending_bytes_human: string|null,
     *     reserved_bytes: int,
     *     reserved_bytes_human: string|null,
     *     quota_bytes: int,
     *     quota_bytes_human: string|null,
     *     remaining_bytes: int|null,
     *     remaining_bytes_human: string|null,
     *     state: string,
     *     collaborative_works: bool,
     *     can_create_workspace: bool,
     *     owned_workspace_used: int,
     *     owned_workspace_limit: int
     * }
     */
    public function usage(string $userId): array
    {
        $used = $this->committedBytes($userId);
        $pending = $this->pendingUploadBytes($userId);
        $reserved = $used + $pending;

        $state = $this->storageStateFor($userId);
        $quota = $this->quotaBytesFor($userId);
        $collab = $state === 'paid';

        $slots = app(WorkspaceAccessService::class);
        $ownedUsed = $slots->countActiveOwned($userId);
        $ownedLimit = $slots->ownedSlotLimit($userId);

        return [
            'used_bytes' => $used,
            'used_bytes_human' => BytePresenter::human($used),
            'pending_bytes' => $pending,
            'pending_bytes_human' => BytePresenter::human($pending),
            'reserved_bytes' => $reserved,
            'reserved_bytes_human' => BytePresenter::human($reserved),
            'quota_bytes' => $quota,
            'quota_bytes_human' => BytePresenter::human($quota),
            'remaining_bytes' => $quota > 0 ? max(0, $quota - $reserved) : null,
            'remaining_bytes_human' => $quota > 0
                ? BytePresenter::human(max(0, $quota - $reserved))
                : null,
            'state' => $state,
            // Paid main/storage-addon only. Free = no collab; grace = renew first.
            'collaborative_works' => $collab,
            'can_create_workspace' => $collab && $ownedUsed < $ownedLimit,
            'owned_workspace_used' => $ownedUsed,
            'owned_workspace_limit' => $ownedLimit,
        ];
    }

    /**
     * Bytes already promoted to HostedFile (soft-deleted excluded by default SoftDeletes).
     */
    public function committedBytes(string $userId): int
    {
        $fileBytes = (int) HostedFile::query()
            ->where('user_id', $userId)
            ->sum('size');

        $variantBytes = (int) FileVariant::query()
            ->ready()
            ->whereHas('file', fn ($q) => $q->where('user_id', $userId))
            ->sum('size');

        return $fileBytes + $variantBytes;
    }

    /**
     * Pending non-expired upload sessions (reservation to reduce TOCTOU over-quota).
     */
    public function pendingUploadBytes(string $userId, ?string $excludeUploadId = null): int
    {
        $query = FileUpload::query()
            ->where('user_id', $userId)
            ->where('status', FileUpload::STATUS_PENDING)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });

        if ($excludeUploadId !== null && $excludeUploadId !== '') {
            $query->whereKeyNot($excludeUploadId);
        }

        return (int) $query->sum('size');
    }

    public function assertCanAccept(string $userId, int $incomingBytes, ?string $excludeUploadId = null): void
    {
        if ($this->entitlements->hasGraceArchivd($userId)) {
            throw new ArchivdException(__('api/archivd.quota_grace_blocked'), 422);
        }

        $quota = $this->quotaBytesFor($userId);

        // True unlimited only if explicitly 0 and not free-tier path (reserved; tiers always > 0).
        if ($quota <= 0) {
            return;
        }

        $reserved = $this->committedBytes($userId) + $this->pendingUploadBytes($userId, $excludeUploadId);

        if ($reserved + $incomingBytes > $quota) {
            throw new ArchivdException(__('api/archivd.quota_exceeded'));
        }
    }

    /**
     * Prefer denorm UserQuota (recomputed on fulfill/expire). Fallback: free tier only.
     */
    public function quotaBytesFor(string $userId): int
    {
        $override = UserQuota::query()
            ->where('user_id', $userId)
            ->value('quota_bytes');

        if ($override !== null) {
            return (int) $override;
        }

        return $this->freeTierQuotaBytes();
    }

    public function freeTierQuotaBytes(): int
    {
        return (int) setting_config('archivd.default_quota_bytes', 5 * 1024 * 1024 * 1024);
    }

    /**
     * @return 'paid'|'grace'|'free'
     */
    public function storageStateFor(string $userId): string
    {
        if ($this->entitlements->hasGraceArchivd($userId)) {
            return 'grace';
        }

        if ($this->entitlements->hasActivePaidArchivd($userId)) {
            return 'paid';
        }

        return 'free';
    }

    /**
     * Guest public links and enabling anyone_with_link require active paid Archivd storage.
     * Free and grace owners cannot expose files/folders publicly (grace = renew first).
     */
    public function ownerCanPublicShare(string $userId): bool
    {
        return $this->storageStateFor($userId) === 'paid';
    }

    /**
     * Enumerate user IDs currently on a given Archivd tier.
     *
     * Paid / grace resolved from active entitlements. Free = Archivd
     * product activations minus paid minus grace. 'n/a' yields an empty
     * collection — only materializes during a partial backfill window.
     *
     * @return list<string>
     */
    public function userIdsForTier(string $tier): array
    {
        return match ($tier) {
            'paid' => $this->paidArchivdUserIds(),
            'grace' => $this->graceArchivdUserIds(),
            'free' => $this->freeArchivdUserIds(),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function paidArchivdUserIds(): array
    {
        return ServiceEntitlement::query()
            ->where('status', EntitlementStatus::Active)
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', ArchivdCatalog::FULFILLMENT_TARGET)
                    ->whereIn('plan_kind', [PlanKind::Main, PlanKind::Addon])
                    ->where('code', '!=', ArchivdCatalog::CODE_FREE)
                    ->where('storage_bytes', '>', 0);
            })
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function graceArchivdUserIds(): array
    {
        return ServiceEntitlement::query()
            ->where('status', EntitlementStatus::Grace)
            ->whereHas('service', function ($q): void {
                $q->where('fulfillment_target', ArchivdCatalog::FULFILLMENT_TARGET)
                    ->whereIn('plan_kind', [PlanKind::Main, PlanKind::Addon])
                    ->where('code', '!=', ArchivdCatalog::CODE_FREE)
                    ->where('storage_bytes', '>', 0);
            })
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Free = Archivd product activation users minus paid minus grace.
     *
     * @return list<string>
     */
    private function freeArchivdUserIds(): array
    {
        $excluded = array_merge($this->paidArchivdUserIds(), $this->graceArchivdUserIds());

        return ProductActivation::query()
            ->where('product_key', 'Archivd')
            ->whereNotIn('user_id', $excluded)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }
}
