<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\UserQuota;
use App\Domain\Service\Services\ServiceEntitlementService;

class QuotaService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
    ) {}

    /**
     * @return array{
     *     used_bytes: int,
     *     pending_bytes: int,
     *     reserved_bytes: int,
     *     quota_bytes: int,
     *     remaining_bytes: int|null,
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
            'pending_bytes' => $pending,
            'reserved_bytes' => $reserved,
            'quota_bytes' => $quota,
            'remaining_bytes' => $quota > 0 ? max(0, $quota - $reserved) : null,
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
        return (int) HostedFile::query()
            ->where('user_id', $userId)
            ->sum('size');
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
        if ($this->entitlements->hasGraceFileHosting($userId)) {
            throw new FileHostingException(__('api/file-hosting.quota_grace_blocked'), 422);
        }

        $quota = $this->quotaBytesFor($userId);

        // True unlimited only if explicitly 0 and not free-tier path (reserved; tiers always > 0).
        if ($quota <= 0) {
            return;
        }

        $reserved = $this->committedBytes($userId) + $this->pendingUploadBytes($userId, $excludeUploadId);

        if ($reserved + $incomingBytes > $quota) {
            throw new FileHostingException(__('api/file-hosting.quota_exceeded'));
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
        return (int) config('file-hosting.default_quota_bytes', 5 * 1024 * 1024 * 1024);
    }

    /**
     * @return 'paid'|'grace'|'free'
     */
    public function storageStateFor(string $userId): string
    {
        if ($this->entitlements->hasGraceFileHosting($userId)) {
            return 'grace';
        }

        if ($this->entitlements->hasActivePaidFileHosting($userId)) {
            return 'paid';
        }

        return 'free';
    }
}
