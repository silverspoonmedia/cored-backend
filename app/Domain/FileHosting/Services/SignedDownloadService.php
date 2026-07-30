<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\FileVariant;
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
    public function resolve(HostedFile $file, bool $inline = false): array
    {
        // Preview (inline) URLs are not cached: the cached row holds the default
        // attachment URL, and mixing modes under one cache column would serve the
        // wrong disposition. Inline is minted on demand.
        if ($inline) {
            return $this->mint($file, $inline);
        }

        if ($file->hasUsableSignedDownloadUrl() && $this->cachedUrlMatchesMode($file)) {
            return [
                'url' => (string) $file->signed_download_url,
                'expires_at' => $file->signed_download_expires_at,
            ];
        }

        return $this->mintAndCache($file);
    }

    /**
     * Mint a short-lived inline URL for a persisted file variant (AI vision).
     *
     * @return array{url: string, expires_at: Carbon}
     */
    public function resolveVariant(FileVariant $variant, bool $inline = true): array
    {
        $variant->loadMissing('file');
        $file = $variant->file;

        if ($file === null) {
            throw new FileHostingException(__('api/file-hosting.download_unavailable'));
        }

        $ttlMinutes = (int) config('microstock.ai.vision_image.signed_url_ttl_minutes', 30);
        $expiresAt = Carbon::now()->addMinutes(max(1, $ttlMinutes));
        $name = $this->variantDownloadName($file->name, $variant);

        $url = $this->mintObjectUrl(
            userId: (string) $file->user_id,
            disk: (string) $variant->disk,
            objectPath: (string) $variant->path,
            expiresAt: $expiresAt,
            downloadName: $name,
            mime: is_string($variant->mime) ? $variant->mime : 'image/jpeg',
            inline: $inline,
        );

        return [
            'url' => $url,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array{url: string, expires_at: Carbon}
     */
    public function mintAndCache(HostedFile $file, bool $inline = false): array
    {
        $result = $this->mint($file, $inline);

        if (! $inline) {
            $file->forceFill([
                'signed_download_url' => $result['url'],
                'signed_download_expires_at' => $result['expires_at'],
            ])->save();
        }

        return $result;
    }

    /**
     * @return array{url: string, expires_at: Carbon}
     */
    private function mint(HostedFile $file, bool $inline): array
    {
        $ttlMinutes = (int) config('file-hosting.signed_download_ttl_minutes', 60);
        $expiresAt = Carbon::now()->addMinutes($ttlMinutes);
        $url = $this->mintUrl($file, $expiresAt, $inline);

        return [
            'url' => $url,
            'expires_at' => $expiresAt,
        ];
    }

    private function mintUrl(HostedFile $file, Carbon $expiresAt, bool $inline = false): string
    {
        return $this->mintObjectUrl(
            userId: (string) $file->user_id,
            disk: (string) $file->disk,
            objectPath: (string) $file->path,
            expiresAt: $expiresAt,
            downloadName: $file->name,
            mime: is_string($file->mime) ? $file->mime : '',
            inline: $inline,
        );
    }

    private function mintObjectUrl(
        string $userId,
        string $disk,
        string $objectPath,
        Carbon $expiresAt,
        string $downloadName,
        string $mime,
        bool $inline,
    ): string {
        if ($this->isDownloadProxyEnabledForDisk($disk)) {
            return $this->proxyObjectDownloadUrl($userId, $disk, $objectPath, $expiresAt, $downloadName, $mime, $inline);
        }

        return $this->temporaryObjectUrl($userId, $disk, $objectPath, $expiresAt, $downloadName, $mime, $inline);
    }

    private function variantDownloadName(string $parentName, FileVariant $variant): string
    {
        $kind = $variant->kind instanceof \App\Domain\FileHosting\Enums\FileVariantKind
            ? $variant->kind->value
            : (string) $variant->kind;
        $base = pathinfo($parentName, PATHINFO_FILENAME) ?: 'asset';

        return $base.'-'.$kind.'.jpg';
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

    private function proxyDownloadUrl(HostedFile $file, Carbon $expiresAt, bool $inline = false): string
    {
        return $this->proxyObjectDownloadUrl(
            (string) $file->user_id,
            (string) $file->disk,
            (string) $file->path,
            $expiresAt,
            $file->name,
            is_string($file->mime) ? $file->mime : '',
            $inline,
        );
    }

    private function proxyObjectDownloadUrl(
        string $userId,
        string $disk,
        string $objectPath,
        Carbon $expiresAt,
        string $downloadName,
        string $mime,
        bool $inline,
    ): string {
        $baseUrl = $this->proxyBaseUrl();
        $token = (string) config('file-hosting.upload_proxy.token', '');
        $alias = $this->targetAliasForDisk($disk);

        if ($baseUrl === '' || $token === '' || $alias === null) {
            throw new FileHostingException(__('api/file-hosting.download_unavailable'));
        }

        $objectKey = $this->objectKeys->objectKey($userId, $objectPath);
        $disposition = $this->contentDisposition($downloadName, $inline ? ($mime !== '' ? $mime : null) : null);
        $contentType = $mime;
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

        return $baseUrl.'/uploader/'.$alias.'/'.$encodedKey.'?'.$query;
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

    private function temporaryUrl(HostedFile $file, Carbon $expiresAt, bool $inline = false): string
    {
        return $this->temporaryObjectUrl(
            (string) $file->user_id,
            (string) $file->disk,
            (string) $file->path,
            $expiresAt,
            $file->name,
            is_string($file->mime) ? $file->mime : '',
            $inline,
        );
    }

    private function temporaryObjectUrl(
        string $userId,
        string $disk,
        string $objectPath,
        Carbon $expiresAt,
        string $downloadName,
        string $mime,
        bool $inline,
    ): string {
        $diskDriver = Storage::disk($disk);

        if (! method_exists($diskDriver, 'temporaryUrl')) {
            throw new FileHostingException(__('api/file-hosting.download_unavailable'));
        }

        $options = [
            'ResponseContentDisposition' => $this->contentDisposition($downloadName, $inline && $mime !== '' ? $mime : null),
        ];

        if ($mime !== '') {
            $options['ResponseContentType'] = $mime;
        }

        $objectKey = $this->objectKeys->objectKey($userId, $objectPath);

        try {
            $url = $diskDriver->temporaryUrl($objectKey, $expiresAt, $options);
        } catch (\Throwable) {
            try {
                $url = $diskDriver->temporaryUrl($objectKey, $expiresAt);
            } catch (\Throwable) {
                throw new FileHostingException(__('api/file-hosting.download_unavailable'));
            }
        }

        if (! is_string($url) || $url === '') {
            throw new FileHostingException(__('api/file-hosting.download_unavailable'));
        }

        return $url;
    }

    /**
     * Build a Content-Disposition header value. When $mime is provided and is
     * safe to render in-browser (image/pdf/audio/video/text), emit `inline` so
     * the browser previews instead of downloading; otherwise `attachment`.
     */
    private function contentDisposition(string $name, ?string $mime = null): string
    {
        $safe = str_replace(['"', "\r", "\n"], '', $name);
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $safe) ?: 'download';

        $type = ($mime !== null && $this->isInlinePreviewableMime($mime)) ? 'inline' : 'attachment';

        return $type.'; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($safe);
    }

    private function isInlinePreviewableMime(string $mime): bool
    {
        $mime = strtolower(trim($mime));

        if ($mime === 'application/pdf') {
            return true;
        }

        foreach (['image/', 'audio/', 'video/', 'text/'] as $prefix) {
            if (str_starts_with($mime, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
