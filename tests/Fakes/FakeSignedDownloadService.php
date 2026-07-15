<?php

namespace Tests\Fakes;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\SignedDownloadService;
use Illuminate\Support\Carbon;

/**
 * Mints deterministic fake signed URLs and honors HostedFile cache columns.
 */
class FakeSignedDownloadService extends SignedDownloadService
{
    /**
     * @return array{url: string, expires_at: Carbon}
     */
    public function mintAndCache(HostedFile $file): array
    {
        $ttlMinutes = (int) config('file-hosting.signed_download_ttl_minutes', 15);
        $expiresAt = Carbon::now()->addMinutes($ttlMinutes);
        $url = 'https://fake-storage.test/download/'.$file->id.'?exp='.$expiresAt->getTimestamp();

        $file->forceFill([
            'signed_download_url' => $url,
            'signed_download_expires_at' => $expiresAt,
        ])->save();

        return [
            'url' => $url,
            'expires_at' => $expiresAt,
        ];
    }
}
