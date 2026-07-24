<?php

namespace Tests\Domain\Vtual\Unit\Integrations;

use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Support\BatchIdChunker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BatchIdChunkerTest extends TestCase
{
    #[Test]
    public function normalize_dedupes_and_trims(): void
    {
        $ids = BatchIdChunker::normalize([' a ', 'b', 'a', '', 'b'], 50);
        $this->assertSame(['a', 'b'], $ids);
    }

    #[Test]
    public function normalize_rejects_empty(): void
    {
        $this->expectException(VtualException::class);
        BatchIdChunker::normalize(['', '  '], 50);
    }

    #[Test]
    public function normalize_hard_fails_above_max(): void
    {
        $ids = array_map(fn (int $i): string => 'id-'.$i, range(1, 51));
        $this->expectException(VtualException::class);
        BatchIdChunker::normalize($ids, 50, 'video ids');
    }

    #[Test]
    public function normalize_allows_exact_max(): void
    {
        $ids = array_map(fn (int $i): string => 'id-'.$i, range(1, 50));
        $this->assertCount(50, BatchIdChunker::normalize($ids, 50));
    }

    #[Test]
    public function chunk_splits_without_hard_fail(): void
    {
        $ids = array_map(fn (int $i): string => 'id-'.$i, range(1, 75));
        $chunks = BatchIdChunker::chunk($ids, 50);
        $this->assertCount(2, $chunks);
        $this->assertCount(50, $chunks[0]);
        $this->assertCount(25, $chunks[1]);
    }
}
