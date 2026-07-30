<?php

namespace Tests\Unit\Support\Storage;

use App\Support\Storage\S3DirectUploadEngine;
use App\Support\Storage\S3DirectUploadException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class S3DirectUploadEngineTest extends TestCase
{
    private S3DirectUploadEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new S3DirectUploadEngine;
    }

    #[Test]
    public function resolve_strategy_uses_multipart_threshold(): void
    {
        $this->assertSame(
            S3DirectUploadEngine::STRATEGY_SINGLE,
            $this->engine->resolveStrategy(1_000, 5_000),
        );

        $this->assertSame(
            S3DirectUploadEngine::STRATEGY_MULTIPART,
            $this->engine->resolveStrategy(5_000, 5_000),
        );
    }

    #[Test]
    public function expected_part_count_for_single_is_one(): void
    {
        $this->assertSame(
            1,
            $this->engine->expectedPartCount(S3DirectUploadEngine::STRATEGY_SINGLE, 10_000, 1_000),
        );
    }

    #[Test]
    public function expected_part_count_ceil_divides_size(): void
    {
        $this->assertSame(
            3,
            $this->engine->expectedPartCount(S3DirectUploadEngine::STRATEGY_MULTIPART, 10_000, 4_000),
        );
    }

    #[Test]
    public function missing_part_numbers_respects_limit(): void
    {
        $missing = $this->engine->missingPartNumbers(
            [['part_number' => 2, 'etag' => 'a', 'size' => 1]],
            partCount: 5,
            maxPartsPerResponse: 2,
        );

        $this->assertSame([1, 3], $missing);
    }

    #[Test]
    public function merge_parts_prefers_incoming_over_recorded(): void
    {
        $merged = $this->engine->mergeParts(
            [
                ['part_number' => 1, 'etag' => 'old', 'size' => 10],
            ],
            [
                ['part_number' => 1, 'etag' => '"new"', 'size' => 20],
                ['part_number' => 2, 'etag' => 'two', 'size' => 5],
            ],
        );

        $this->assertSame([
            ['part_number' => 1, 'etag' => 'new', 'size' => 20],
            ['part_number' => 2, 'etag' => 'two', 'size' => 5],
        ], $merged);
    }

    #[Test]
    public function assert_all_parts_present_throws_when_incomplete(): void
    {
        $this->expectException(S3DirectUploadException::class);
        $this->expectExceptionMessage(S3DirectUploadException::INCOMPLETE_PARTS);

        $this->engine->assertAllPartsPresent(
            [['part_number' => 1, 'etag' => 'a', 'size' => 1]],
            expected: 2,
        );
    }

    #[Test]
    public function assert_upload_allowed_rejects_empty_name(): void
    {
        $this->expectException(S3DirectUploadException::class);
        $this->expectExceptionMessage(S3DirectUploadException::INVALID_NAME);

        $this->engine->assertUploadAllowed('  ', 'image/png', 100, 1_000_000);
    }

    #[Test]
    public function assert_upload_allowed_rejects_disallowed_mime_prefix(): void
    {
        $this->expectException(S3DirectUploadException::class);
        $this->expectExceptionMessage(S3DirectUploadException::MIME_NOT_ALLOWED);

        $this->engine->assertUploadAllowed(
            'x.bin',
            'application/octet-stream',
            100,
            1_000_000,
            ['image/'],
        );
    }

    #[Test]
    public function assert_upload_allowed_accepts_matching_mime_prefix(): void
    {
        $this->engine->assertUploadAllowed(
            'x.png',
            'image/png',
            100,
            1_000_000,
            ['image/'],
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function default_config_chunk_and_threshold_are_ten_mib(): void
    {
        $this->assertSame(10 * 1024 * 1024, (int) config('file-hosting.chunk_size_bytes'));
        $this->assertSame(10 * 1024 * 1024, (int) config('file-hosting.multipart_threshold_bytes'));
    }

    #[Test]
    public function upload_proxy_disabled_when_base_url_empty(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => '',
            'file-hosting.upload_proxy.token' => 'token-long-enough',
            'file-hosting.upload_proxy.disk_targets' => [
                's3FileHostingCe' => 'b2-main',
                's3Custom' => 'b2-main',
            ],
        ]);

        $this->assertFalse($this->engine->isUploadProxyEnabled());
    }

    #[Test]
    public function proxy_upload_parts_builds_worker_urls_and_auth_headers(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => 'https://proxy.example.workers.dev',
            'file-hosting.upload_proxy.token' => 'strong-upload-token-16',
            'file-hosting.upload_proxy.disk_targets' => [
                's3Custom' => 'b2-main',
            ],
        ]);

        $this->assertTrue($this->engine->isUploadProxyEnabled());

        $parts = $this->engine->proxyUploadParts(
            's3Custom',
            'file-hosting/user-id/upload-id/abc123.bin',
            S3DirectUploadEngine::STRATEGY_MULTIPART,
            'application/pdf',
            's3-upload-id-xyz',
            [1, 2],
        );

        $this->assertCount(2, $parts);
        $this->assertSame(1, $parts[0]['part_number']);
        $this->assertSame(
            'https://proxy.example.workers.dev/uploader/b2-main/file-hosting/user-id/upload-id/abc123.bin?partNumber=1&uploadId=s3-upload-id-xyz',
            $parts[0]['url'],
        );
        $this->assertSame(
            'https://proxy.example.workers.dev/uploader/b2-main/file-hosting/user-id/upload-id/abc123.bin?partNumber=2&uploadId=s3-upload-id-xyz',
            $parts[1]['url'],
        );
        $this->assertSame(['Bearer strong-upload-token-16'], $parts[0]['headers']['Authorization']);
        $this->assertSame(['b2-main'], $parts[0]['headers']['X-S3-Target']);
        $this->assertSame(['application/pdf'], $parts[0]['headers']['Content-Type']);
    }

    #[Test]
    public function proxy_upload_parts_single_strategy_omits_multipart_query(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => 'https://proxy.example.workers.dev/',
            'file-hosting.upload_proxy.token' => 'strong-upload-token-16',
            'file-hosting.upload_proxy.disk_targets' => [
                's3Custom' => 'b2-main',
            ],
        ]);

        $parts = $this->engine->proxyUploadParts(
            's3Custom',
            'file-hosting/u/up/obj.bin',
            S3DirectUploadEngine::STRATEGY_SINGLE,
            'image/png',
            null,
            [1],
        );

        $this->assertCount(1, $parts);
        $this->assertSame(
            'https://proxy.example.workers.dev/uploader/b2-main/file-hosting/u/up/obj.bin',
            $parts[0]['url'],
        );
    }

    #[Test]
    public function proxy_upload_parts_fails_closed_when_token_missing(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => 'https://proxy.example.workers.dev',
            'file-hosting.upload_proxy.token' => '',
            'file-hosting.upload_proxy.disk_targets' => [
                's3Custom' => 'b2-main',
            ],
        ]);

        $this->expectException(S3DirectUploadException::class);
        $this->expectExceptionMessage(S3DirectUploadException::PROXY_MISCONFIGURED);

        $this->engine->proxyUploadParts(
            's3Custom',
            'file-hosting/a.bin',
            S3DirectUploadEngine::STRATEGY_SINGLE,
            'text/plain',
            null,
            [1],
        );
    }

    #[Test]
    public function proxy_upload_parts_fails_closed_when_disk_alias_missing(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => 'https://proxy.example.workers.dev',
            'file-hosting.upload_proxy.token' => 'strong-upload-token-16',
            'file-hosting.upload_proxy.disk_targets' => [],
        ]);

        $this->expectException(S3DirectUploadException::class);
        $this->expectExceptionMessage(S3DirectUploadException::PROXY_MISCONFIGURED);

        $this->engine->proxyUploadParts(
            's3Custom',
            'file-hosting/a.bin',
            S3DirectUploadEngine::STRATEGY_SINGLE,
            'text/plain',
            null,
            [1],
        );
    }

    #[Test]
    public function presign_parts_uses_proxy_when_enabled_without_s3_disk(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => 'https://proxy.example.workers.dev',
            'file-hosting.upload_proxy.token' => 'strong-upload-token-16',
            'file-hosting.upload_proxy.disk_targets' => [
                's3Custom' => 'b2-main',
            ],
        ]);

        $parts = $this->engine->presignParts(
            's3Custom',
            'file-hosting/x.bin',
            S3DirectUploadEngine::STRATEGY_MULTIPART,
            'application/octet-stream',
            'uid-1',
            [3],
            15,
        );

        $this->assertSame(
            'https://proxy.example.workers.dev/uploader/b2-main/file-hosting/x.bin?partNumber=3&uploadId=uid-1',
            $parts[0]['url'],
        );
    }

    #[Test]
    public function encode_proxy_object_key_encodes_segments(): void
    {
        $this->assertSame(
            'file-hosting/user%20id/obj.bin',
            $this->engine->encodeProxyObjectKey('file-hosting/user id/obj.bin'),
        );
    }

    #[Test]
    public function format_etag_quotes_once(): void
    {
        $this->assertSame('"abc"', $this->engine->formatEtag('abc'));
        $this->assertSame('"abc"', $this->engine->formatEtag('"abc"'));
        $this->assertSame('"abc"', $this->engine->formatEtag('""abc""'));
    }
}
