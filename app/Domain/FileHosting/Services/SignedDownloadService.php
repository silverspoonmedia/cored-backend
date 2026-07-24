<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Mints and caches download URLs for HostedFile rows.
 *
 * When file-hosting.upload_proxy is configured, URLs point at the CF Worker
 * (HMAC query) so B2 egress rides Bandwidth Alliance. Otherwise mints a direct
 * S3 temporary URL. Object keys stay random; disposition maps the browser
 * filename to the human-readable DB name.
 */
class SignedDownloadService
{
    public function __construct(
        private readonly ObjectKeyResolver $objectKeys = new ObjectKeyResolver,
    ) {}

    /**
     * @return array{url: string, expires_at: Carbon}
     */
    public function resolve(HostedFile $file): array
    {
        if ($file->hasUsableSignedDownloadUrl() && $this->cachedUrlMatchesMode($file)) {
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
        $url = $this->mintUrl($file, $expiresAt);

        $file->forceFill([
            'signed_download_url' => $url,
            'signed_download_expires_at' => $expiresAt,
        ])->save();

        return [
            'url' => $url,
            'expires_at' => $expiresAt,
        ];
    }

    private function mintUrl(HostedFile $file, Carbon $expiresAt): string
    {
        if ($this->isDownloadProxyEnabledForDisk((string) $file->disk)) {
            return $this->proxyDownloadUrl($file, $expiresAt);
        }

        return $this->temporaryUrl($file, $expiresAt);
    }

    /**
     * True when cached URL host matches current proxy mode (avoids stale B2
     * URLs after proxy is enabled, and stale Worker URLs after proxy is off).
     */
    private function cachedUrlMatchesMode(HostedFile $file): bool
    {
        $cached = (string) $file->signed_download_url;
        if ($cached === '') {
            return false;
        }

        $proxyBase = $this->proxyBaseUrl();
        $proxyOn = $this->isDownloadProxyEnabledForDisk((string) $file->disk);

        if ($proxyOn) {
            return $this->urlStartsWithBase($cached, $proxyBase);
        }

        // Proxy off: reject cache that still points at the configured Worker origin.
        if ($proxyBase !== '' && $this->urlStartsWithBase($cached, $proxyBase)) {
            return false;
        }

        return true;
    }

    private function isDownloadProxyEnabledForDisk(string $disk): bool
    {
        if ($this->proxyBaseUrl() === '') {
            return false;
        }

        $token = (string) config('file-hosting.upload_proxy.token', '');
        if ($token === '') {
            return false;
        }

        return $this->targetAliasForDisk($disk) !== null;
    }

    private function proxyBaseUrl(): string
    {
        return rtrim((string) config('file-hosting.upload_proxy.base_url', ''), '/');
    }

    private function targetAliasForDisk(string $disk): ?string
    {
        $diskTargets = (array) config('file-hosting.upload_proxy.disk_targets', []);
        $alias = $diskTargets[$disk] ?? null;

        if (! is_string($alias) || trim($alias) === '') {
            return null;
        }

        return trim($alias);
    }

    private function proxyDownloadUrl(HostedFile $file, Carbon $expiresAt): string
    {
        $baseUrl = $this->proxyBaseUrl();
        $token = (string) config('file-hosting.upload_proxy.token', '');
        $alias = $this->targetAliasForDisk((string) $file->disk);

        if ($baseUrl === '' || $token === '' || $alias === null) {
            throw new FileHostingException(__('api/file-hosting.download_unavailable'));
        }

        $objectKey = $this->objectKeys->objectKey($file->user_id, $file->path);
        $disposition = $this->contentDisposition($file->name);
        $contentType = is_string($file->mime) ? $file->mime : '';
        $exp = (string) $expiresAt->getTimestamp();

        $payload = $this->downloadSignaturePayload(
            $alias,
            $objectKey,
            $exp,
            $disposition,
            $contentType,
        );
        $sig = hash_hmac('sha256', $payload, $token);

        $query = http_build_query([
            'exp' => $exp,
            'disp' => $disposition,
            'ctype' => $contentType,
            'sig' => $sig,
        ], '', '&', PHP_QUERY_RFC3986);

        $encodedKey = $this->encodeObjectKeyPath($objectKey);

        return $baseUrl.'/t/'.$alias.'/'.$encodedKey.'?'.$query;
    }

    /**
     * Must match Worker buildDownloadSignaturePayload (LF-separated).
     */
    public function downloadSignaturePayload(
        string $alias,
        string $objectKey,
        string $exp,
        string $disposition,
        string $contentType,
    ): string {
        return implode("\n", [
            'GET',
            $alias,
            $objectKey,
            $exp,
            $disposition,
            $contentType,
        ]);
    }

    private function encodeObjectKeyPath(string $objectKey): string
    {
        return implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            array_values(array_filter(explode('/', $objectKey), static fn (string $s): bool => $s !== '')),
        ));
    }

    private function urlStartsWithBase(string $url, string $base): bool
    {
        if ($base === '') {
            return false;
        }

        $normalizedUrl = rtrim($url, '/');
        $normalizedBase = rtrim($base, '/');

        return str_starts_with($normalizedUrl, $normalizedBase.'/')
            || $normalizedUrl === $normalizedBase
            || str_starts_with($normalizedUrl, $normalizedBase.'?');
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

        $objectKey = $this->objectKeys->objectKey($file->user_id, $file->path);

        try {
            $url = $disk->temporaryUrl($objectKey, $expiresAt, $options);
        } catch (\Throwable) {
            // Some adapters reject options; retry bare temporaryUrl.
            try {
                $url = $disk->temporaryUrl($objectKey, $expiresAt);
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
