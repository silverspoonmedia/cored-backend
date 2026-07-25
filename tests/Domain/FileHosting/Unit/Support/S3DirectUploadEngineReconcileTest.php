<?php

namespace Tests\Domain\FileHosting\Unit\Support;

use App\Support\Storage\S3DirectUploadEngine;
use Tests\TestCase;

/**
 * Pure part-reconciliation logic for resumable multipart uploads (no S3 / DB).
 */
class S3DirectUploadEngineReconcileTest extends TestCase
{
    private S3DirectUploadEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new S3DirectUploadEngine;
    }

    public function test_reconcile_keeps_only_parts_s3_reports(): void
    {
        $db = [
            ['part_number' => 1, 'etag' => 'a', 'size' => 10],
            // Part 2 was claimed by the client but never actually landed on S3.
            ['part_number' => 2, 'etag' => 'b', 'size' => 10],
        ];
        $s3 = [
            ['part_number' => 1, 'etag' => 'a', 'size' => 10],
        ];

        $this->assertSame(
            [['part_number' => 1, 'etag' => 'a', 'size' => 10]],
            $this->engine->reconcileParts($db, $s3),
        );
    }

    public function test_reconcile_prefers_s3_truth_and_adopts_unknown_parts(): void
    {
        $db = [
            ['part_number' => 1, 'etag' => 'stale', 'size' => 5],
        ];
        $s3 = [
            // S3 etag wins over the DB's stale value.
            ['part_number' => 1, 'etag' => 'fresh', 'size' => 10],
            // Present on S3 but never recorded in the DB — adopted.
            ['part_number' => 2, 'etag' => 'c', 'size' => 10],
        ];

        $this->assertSame([
            ['part_number' => 1, 'etag' => 'fresh', 'size' => 10],
            ['part_number' => 2, 'etag' => 'c', 'size' => 10],
        ], $this->engine->reconcileParts($db, $s3));
    }

    public function test_reconcile_falls_back_to_db_for_missing_s3_fields(): void
    {
        $db = [
            ['part_number' => 1, 'etag' => 'db-etag', 'size' => 42],
        ];
        // S3 reports the part but with an empty etag / zero size (odd provider).
        $s3 = [
            ['part_number' => 1, 'etag' => '', 'size' => 0],
        ];

        $this->assertSame([
            ['part_number' => 1, 'etag' => 'db-etag', 'size' => 42],
        ], $this->engine->reconcileParts($db, $s3));
    }

    public function test_reconcile_sorts_and_strips_quotes(): void
    {
        $s3 = [
            ['part_number' => 3, 'etag' => '"z"', 'size' => 1],
            ['part_number' => 1, 'etag' => '"x"', 'size' => 1],
            ['part_number' => 2, 'etag' => '"y"', 'size' => 1],
        ];

        $this->assertSame([
            ['part_number' => 1, 'etag' => 'x', 'size' => 1],
            ['part_number' => 2, 'etag' => 'y', 'size' => 1],
            ['part_number' => 3, 'etag' => 'z', 'size' => 1],
        ], $this->engine->reconcileParts([], $s3));
    }
}
