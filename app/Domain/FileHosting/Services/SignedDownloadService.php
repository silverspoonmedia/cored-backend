<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\HostedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Mints and caches S3-compatible temporary download URLs.
 *
 * Object keys stay random; ResponseContentDisposition maps the browser
 * filename to the human-readable DB name.
 */
class SignedDownloadService
{
    /**
     * @return array{url: string, expires_at: Carbon}
     */
    public function resolve(HostedFile $file): array
    {
        if ($file->hasUsableSignedDownloadUrl()) {
            return [
                'url' => (string) $file->signed_download_url,
                'expires_at' => $file->signed_download_expires_at,
            ];
        }

        return $this->mintAndCache($file);
    }

    /**
     * @return array{url: string, expires_at: Carbon}
     */
    public function mintAndCache(HostedFile $file): array
    {
        $ttlMinutes = (int) config('file-hosting.signed_download_ttl_minutes', 15);
        $expiresAt = Carbon::now()->addMinutes($ttlMinutes);
        $url = $this->temporaryUrl($file, $expiresAt);

        $file->forceFill([
            'signed_download_url' => $url,
            'signed_download_expires_at' => $expiresAt,
        ])->save();

        return [
            'url' => $url,
            'expires_at' => $expiresAt,
        ];
    }

    private function temporaryUrl(HostedFile $file, Carbon $expiresAt): string
    {
        $disk = Storage::disk($file->disk);

        if (! method_exists($disk, 'temporaryUrl')) {
            throw new FileHostingException(__('api/file-hosting.download_unavailable'));
        }

        $options = [
            'ResponseContentDisposition' => $this->contentDisposition($file->name),
        ];

        if (is_string($file->mime) && $file->mime !== '') {
            $options['ResponseContentType'] = $file->mime;
        }

        try {
            $url = $disk->temporaryUrl($file->path, $expiresAt, $options);
        } catch (\Throwable) {
            // Some adapters reject options; retry bare temporaryUrl.
            try {
                $url = $disk->temporaryUrl($file->path, $expiresAt);
            } catch (\Throwable) {
                throw new FileHostingException(__('api/file-hosting.download_unavailable'));
            }
        }

        if (! is_string($url) || $url === '') {
            throw new FileHostingException(__('api/file-hosting.download_unavailable'));
        }

        return $url;
    }

    private function contentDisposition(string $name): string
    {
        $safe = str_replace(['"', "\r", "\n"], '', $name);
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $safe) ?: 'download';

        return 'attachment; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($safe);
    }
}
