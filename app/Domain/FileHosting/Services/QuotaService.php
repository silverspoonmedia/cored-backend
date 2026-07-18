<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\UserQuota;
use App\Domain\Service\Services\ServiceEntitlementService;

class QuotaService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
    ) {}

    /**
     * @return array{used_bytes: int, quota_bytes: int, remaining_bytes: int|null, state: string}
     */
    public function usage(string $userId): array
    {
        $used = (int) HostedFile::query()
            ->where('user_id', $userId)
            ->sum('size');

        $state = $this->storageStateFor($userId);
        $quota = $this->quotaBytesFor($userId);

        return [
            'used_bytes' => $used,
            'quota_bytes' => $quota,
            'remaining_bytes' => $quota > 0 ? max(0, $quota - $used) : null,
            'state' => $state,
        ];
    }

    public function assertCanAccept(string $userId, int $incomingBytes): void
    {
        if ($this->entitlements->hasGraceFileHosting($userId)) {
            throw new FileHostingException(__('api/file-hosting.quota_grace_blocked'), 422);
        }

        $quota = $this->quotaBytesFor($userId);

        // True unlimited only if explicitly 0 and not free-tier path (reserved; tiers always > 0).
        if ($quota <= 0) {
            return;
        }

        $usage = $this->usage($userId);

        if ($usage['used_bytes'] + $incomingBytes > $quota) {
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
