<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\HostedFile;

class QuotaService
{
    /**
     * @return array{used_bytes: int, quota_bytes: int, remaining_bytes: int|null}
     */
    public function usage(string $userId): array
    {
        $used = (int) HostedFile::query()
            ->where('user_id', $userId)
            ->sum('size');

        $quota = (int) config('file-hosting.default_quota_bytes', 0);

        return [
            'used_bytes' => $used,
            'quota_bytes' => $quota,
            'remaining_bytes' => $quota > 0 ? max(0, $quota - $used) : null,
        ];
    }

    public function assertCanAccept(string $userId, int $incomingBytes): void
    {
        $quota = (int) config('file-hosting.default_quota_bytes', 0);

        if ($quota <= 0) {
            return;
        }

        $usage = $this->usage($userId);

        if ($usage['used_bytes'] + $incomingBytes > $quota) {
            throw new FileHostingException(__('api/file-hosting.quota_exceeded'));
        }
    }
}
