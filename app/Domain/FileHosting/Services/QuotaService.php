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
        if ($this->entitlements->graceFileHostingFor($userId) !== null) {
            throw new FileHostingException(__('api/file-hosting.quota_grace_blocked'), 422);
        }

        $quota = $this->quotaBytesFor($userId);

        if ($quota <= 0) {
            return;
        }

        $usage = $this->usage($userId);

        if ($usage['used_bytes'] + $incomingBytes > $quota) {
            throw new FileHostingException(__('api/file-hosting.quota_exceeded'));
        }
    }

    /**
     * Active unlimited entitlement → 0 (unlimited).
     * Grace → still report previous override or unlimited for display; uploads blocked separately.
     * Else UserQuota override or free-tier default.
     */
    public function quotaBytesFor(string $userId): int
    {
        if ($this->entitlements->activeUnlimitedFileHostingFor($userId) !== null) {
            return 0;
        }

        if ($this->entitlements->graceFileHostingFor($userId) !== null) {
            // Grace: keep files; block uploads only. Report unlimited so UI shows grace messaging separately.
            return 0;
        }

        $override = UserQuota::query()
            ->where('user_id', $userId)
            ->value('quota_bytes');

        if ($override !== null) {
            return (int) $override;
        }

        return (int) config('file-hosting.default_quota_bytes', 5 * 1024 * 1024 * 1024);
    }

    public function freeTierQuotaBytes(): int
    {
        return (int) config('file-hosting.default_quota_bytes', 5 * 1024 * 1024 * 1024);
    }

    /**
     * @return 'unlimited'|'grace'|'free'
     */
    public function storageStateFor(string $userId): string
    {
        if ($this->entitlements->activeUnlimitedFileHostingFor($userId) !== null) {
            return 'unlimited';
        }

        if ($this->entitlements->graceFileHostingFor($userId) !== null) {
            return 'grace';
        }

        return 'free';
    }
}
