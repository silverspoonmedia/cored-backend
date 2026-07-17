<?php

namespace App\Support\Storage;

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Shared S3-compatible multipart / single-PUT presign engine.
 *
 * Domain upload services own session models, ownership, quota, and path layout;
 * this class owns only storage mechanics so FileHosting (and other upload surfaces) stay DRY.
 */
class S3DirectUploadEngine
{
    public const STRATEGY_SINGLE = 'single';

    public const STRATEGY_MULTIPART = 'multipart';

    public function resolveStrategy(int $size, int $multipartThresholdBytes): string
    {
        return $size < $multipartThresholdBytes
            ? self::STRATEGY_SINGLE
            : self::STRATEGY_MULTIPART;
    }

    public function expectedPartCount(string $strategy, int $size, int $chunkSize): int
    {
        if ($strategy === self::STRATEGY_SINGLE) {
            return 1;
        }

        return (int) max(1, (int) ceil($size / max(1, $chunkSize)));
    }

    /**
     * @param  list<string>  $allowedMimePrefixes  Empty = allow any mime
     *
     * @throws S3DirectUploadException
     */
    public function assertUploadAllowed(
        string $name,
        string $mime,
        int $size,
        int $maxFileSizeBytes,
        array $allowedMimePrefixes = [],
    ): void {
        if ($size <= 0) {
            throw new S3DirectUploadException(S3DirectUploadException::INVALID_SIZE);
        }

        if ($size > $maxFileSizeBytes) {
            throw new S3DirectUploadException(S3DirectUploadException::FILE_TOO_LARGE);
        }

        if (trim($name) === '') {
            throw new S3DirectUploadException(S3DirectUploadException::INVALID_NAME);
        }

        if ($allowedMimePrefixes === []) {
            return;
        }

        foreach ($allowedMimePrefixes as $prefix) {
            if (is_string($prefix) && $prefix !== '' && str_starts_with($mime, $prefix)) {
                return;
            }
        }

        throw new S3DirectUploadException(S3DirectUploadException::MIME_NOT_ALLOWED);
    }

    /**
     * @return string S3 multipart upload id
     *
     * @throws S3DirectUploadException
     */
    public function createMultipartUpload(string $diskName, string $path, string $mime): string
    {
        $result = $this->s3Client($diskName)->createMultipartUpload([
            'Bucket' => $this->bucket($diskName),
            'Key' => $path,
            'ContentType' => $mime,
        ]);

        return (string) $result['UploadId'];
    }

    /**
     * @throws S3DirectUploadException
     */
    public function abortMultipartUpload(string $diskName, string $path, string $s3UploadId): void
    {
        $this->s3Client($diskName)->abortMultipartUpload([
            'Bucket' => $this->bucket($diskName),
            'Key' => $path,
            'UploadId' => $s3UploadId,
        ]);
    }

    /**
     * @param  list<array{part_number: int, etag: string, size?: int}>  $parts
     *
     * @throws S3DirectUploadException
     */
    public function completeMultipartUpload(
        string $diskName,
        string $path,
        string $s3UploadId,
        array $parts,
    ): void {
        $this->s3Client($diskName)->completeMultipartUpload([
            'Bucket' => $this->bucket($diskName),
            'Key' => $path,
            'UploadId' => $s3UploadId,
            'MultipartUpload' => [
                'Parts' => array_map(
                    fn (array $part): array => [
                        'ETag' => '"'.$part['etag'].'"',
                        'PartNumber' => $part['part_number'],
                    ],
                    $parts,
                ),
            ],
        ]);
    }

    /**
     * @param  list<int>  $partNumbers
     * @return list<array{part_number: int, url: string, headers?: array<string, list<string>>}>
     *
     * @throws S3DirectUploadException
     */
    public function presignParts(
        string $diskName,
        string $path,
        string $strategy,
        string $mime,
        ?string $s3UploadId,
        array $partNumbers,
        int $presignTtlMinutes,
    ): array {
        if ($partNumbers === []) {
            return [];
        }

        if ($this->isUploadProxyEnabled()) {
            return $this->proxyUploadParts(
                $diskName,
                $path,
                $strategy,
                $mime,
                $s3UploadId,
                $partNumbers,
            );
        }

        $disk = $this->diskAdapter($diskName);
        $expiresAt = Carbon::now()->addMinutes($presignTtlMinutes);

        if ($strategy === self::STRATEGY_SINGLE) {
            $payload = $disk->temporaryUploadUrl($path, $expiresAt, [
                'ContentType' => $mime,
            ]);

            return [[
                'part_number' => 1,
                'url' => $payload['url'],
                'headers' => $payload['headers'] ?? [],
            ]];
        }

        $client = $disk->getClient();
        $bucket = $this->bucket($diskName);
        $signed = [];

        foreach ($partNumbers as $partNumber) {
            $command = $client->getCommand('UploadPart', [
                'Bucket' => $bucket,
                'Key' => $path,
                'UploadId' => $s3UploadId,
                'PartNumber' => $partNumber,
            ]);

            $request = $client->createPresignedRequest($command, $expiresAt);
            $signed[] = [
                'part_number' => $partNumber,
                'url' => (string) $request->getUri(),
                'headers' => $request->getHeaders(),
            ];
        }

        return $signed;
    }

    /**
     * Whether optional CF Worker proxy is configured (base_url non-empty).
     * Token + disk alias are validated when building proxy URLs (fail closed).
     */
    public function isUploadProxyEnabled(): bool
    {
        $baseUrl = (string) config('file-hosting.upload_proxy.base_url', '');

        return $baseUrl !== '';
    }

    /**
     * Build Worker PUT / UploadPart URLs (no S3 browser presign).
     * Worker re-signs with target credentials and streams the body.
     *
     * @param  list<int>  $partNumbers
     * @return list<array{part_number: int, url: string, headers: array<string, list<string>>}>
     *
     * @throws S3DirectUploadException
     */
    public function proxyUploadParts(
        string $diskName,
        string $path,
        string $strategy,
        string $mime,
        ?string $s3UploadId,
        array $partNumbers,
    ): array {
        if ($partNumbers === []) {
            return [];
        }

        $baseUrl = rtrim((string) config('file-hosting.upload_proxy.base_url', ''), '/');
        $token = (string) config('file-hosting.upload_proxy.token', '');
        $diskTargets = (array) config('file-hosting.upload_proxy.disk_targets', []);
        $alias = isset($diskTargets[$diskName]) ? (string) $diskTargets[$diskName] : '';

        if ($baseUrl === '' || $token === '' || $alias === '') {
            throw new S3DirectUploadException(S3DirectUploadException::PROXY_MISCONFIGURED);
        }

        if ($strategy === self::STRATEGY_MULTIPART && ($s3UploadId === null || $s3UploadId === '')) {
            throw new S3DirectUploadException(S3DirectUploadException::INCOMPLETE_PARTS);
        }

        $headers = [
            'Authorization' => ['Bearer '.$token],
            'X-S3-Target' => [$alias],
            'Content-Type' => [$mime !== '' ? $mime : 'application/octet-stream'],
        ];

        $keyPath = $this->encodeProxyObjectKey($path);
        $objectUrl = $baseUrl.'/t/'.rawurlencode($alias).'/'.$keyPath;
        $parts = [];

        foreach ($partNumbers as $partNumber) {
            $partNumber = (int) $partNumber;

            if ($strategy === self::STRATEGY_SINGLE) {
                $parts[] = [
                    'part_number' => 1,
                    'url' => $objectUrl,
                    'headers' => $headers,
                ];

                continue;
            }

            $query = http_build_query([
                'partNumber' => $partNumber,
                'uploadId' => $s3UploadId,
            ]);

            $parts[] = [
                'part_number' => $partNumber,
                'url' => $objectUrl.'?'.$query,
                'headers' => $headers,
            ];
        }

        return $parts;
    }

    /**
     * URL-encode each path segment for Worker /t/{alias}/{key...} routes.
     */
    public function encodeProxyObjectKey(string $path): string
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '') {
            return '';
        }

        return implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', $trimmed),
        ));
    }

    /**
     * @param  list<array{part_number: int, etag?: string, size?: int}>  $recordedParts
     * @return list<int>
     */
    public function missingPartNumbers(array $recordedParts, int $partCount, int $maxPartsPerResponse): array
    {
        $recorded = array_column($recordedParts, 'part_number');
        $missing = [];

        for ($part = 1; $part <= $partCount; $part++) {
            if (! in_array($part, $recorded, true)) {
                $missing[] = $part;
            }
        }

        return array_slice($missing, 0, max(0, $maxPartsPerResponse));
    }

    /**
     * @param  list<array{part_number: int, etag: string, size?: int}>  $recorded
     * @param  list<array{part_number?: int, etag?: string, size?: int}>|null  $incoming
     * @return list<array{part_number: int, etag: string, size: int}>
     */
    public function mergeParts(array $recorded, ?array $incoming): array
    {
        $byNumber = [];

        foreach ($recorded as $part) {
            $byNumber[(int) $part['part_number']] = [
                'part_number' => (int) $part['part_number'],
                'etag' => trim((string) $part['etag'], '"'),
                'size' => (int) ($part['size'] ?? 0),
            ];
        }

        foreach ($incoming ?? [] as $part) {
            $partNumber = (int) ($part['part_number'] ?? 0);
            $etag = trim((string) ($part['etag'] ?? ''), '"');

            if ($partNumber < 1 || $etag === '') {
                continue;
            }

            $byNumber[$partNumber] = [
                'part_number' => $partNumber,
                'etag' => $etag,
                'size' => (int) ($part['size'] ?? 0),
            ];
        }

        ksort($byNumber);

        return array_values($byNumber);
    }

    /**
     * @param  list<array{part_number: int, etag: string, size?: int}>  $parts
     *
     * @throws S3DirectUploadException
     */
    public function assertAllPartsPresent(array $parts, int $expected): void
    {
        if (count($parts) !== $expected) {
            throw new S3DirectUploadException(S3DirectUploadException::INCOMPLETE_PARTS);
        }

        for ($part = 1; $part <= $expected; $part++) {
            $found = false;

            foreach ($parts as $record) {
                if ((int) $record['part_number'] === $part) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                throw new S3DirectUploadException(S3DirectUploadException::INCOMPLETE_PARTS);
            }
        }
    }

    /**
     * @throws S3DirectUploadException
     */
    public function diskAdapter(string $diskName): AwsS3V3Adapter
    {
        $disk = Storage::disk($diskName);

        if (! $disk instanceof AwsS3V3Adapter) {
            throw new S3DirectUploadException(S3DirectUploadException::UNSUPPORTED_DISK);
        }

        return $disk;
    }

    /**
     * @throws S3DirectUploadException
     */
    public function s3Client(string $diskName): S3Client
    {
        return $this->diskAdapter($diskName)->getClient();
    }

    /**
     * @throws S3DirectUploadException
     */
    public function bucket(string $diskName): string
    {
        $bucket = config('filesystems.disks.'.$diskName.'.bucket');

        if (! is_string($bucket) || $bucket === '') {
            throw new S3DirectUploadException(S3DirectUploadException::UNSUPPORTED_DISK);
        }

        return $bucket;
    }
}
