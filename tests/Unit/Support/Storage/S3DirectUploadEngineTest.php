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
}
