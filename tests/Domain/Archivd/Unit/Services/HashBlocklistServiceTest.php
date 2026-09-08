<?php

namespace Tests\Domain\Archivd\Unit\Services;

use App\Domain\Archivd\Services\HashBlocklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HashBlocklistServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_set_skips_query_when_checksums_are_empty(): void
    {
        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains((string) $query->sql, 'archivd_hash_blocklist')) {
                $queries++;
            }
        });

        $this->assertSame([], app(HashBlocklistService::class)->blockedSet(['', null, 12]));
        $this->assertSame(0, $queries);
    }

    public function test_blocked_set_normalizes_case_and_returns_matches_in_one_query(): void
    {
        $blocked = hash('sha256', 'listed');
        $other = hash('sha256', 'other');
        app(HashBlocklistService::class)->block($blocked);

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains((string) $query->sql, 'archivd_hash_blocklist')) {
                $queries++;
            }
        });

        $set = app(HashBlocklistService::class)->blockedSet([
            strtoupper($blocked),
            $other,
            $blocked,
            '',
        ]);

        $this->assertSame([$blocked => true], $set);
        $this->assertSame(1, $queries);
    }
}
