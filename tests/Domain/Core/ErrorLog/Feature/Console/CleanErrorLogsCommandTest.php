<?php

namespace Tests\Domain\Core\ErrorLog\Feature\Console;

use App\Domain\ErrorLog\Models\ErrorLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanErrorLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_deletes_only_old_groups(): void
    {
        $old = ErrorLog::query()->create([
            'fingerprint' => hash('sha1', 'old'),
            'type' => ErrorLog::TYPE_LOG,
            'level' => 'error',
            'exception_class' => null,
            'message' => 'old',
            'file' => null,
            'line' => null,
            'trace' => null,
            'context' => [],
            'occurrence_count' => 1,
            'first_seen_at' => now()->subDays(120),
            'last_seen_at' => now()->subDays(100),
            'status' => ErrorLog::STATUS_OPEN,
        ]);

        $fresh = ErrorLog::query()->create([
            'fingerprint' => hash('sha1', 'fresh'),
            'type' => ErrorLog::TYPE_LOG,
            'level' => 'error',
            'exception_class' => null,
            'message' => 'fresh',
            'file' => null,
            'line' => null,
            'trace' => null,
            'context' => [],
            'occurrence_count' => 1,
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now(),
            'status' => ErrorLog::STATUS_OPEN,
        ]);

        $this->artisan('errorlog:clean', ['--days' => 90])
            ->assertSuccessful();

        $this->assertDatabaseMissing('error_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('error_logs', ['id' => $fresh->id]);
    }
}
