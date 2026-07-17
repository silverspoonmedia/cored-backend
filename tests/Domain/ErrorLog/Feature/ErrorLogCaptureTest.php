<?php

namespace Tests\Domain\ErrorLog\Feature;

use App\Domain\ErrorLog\Models\ErrorLog;
use App\Domain\ErrorLog\Support\ErrorLogRecorder;
use App\Exceptions\BusinessRuleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ErrorLogCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_exception_creates_grouped_row(): void
    {
        $recorder = app(ErrorLogRecorder::class);

        // Same throw site → same file/line fingerprint; only message noise differs.
        $throw = static function (string $message): \RuntimeException {
            return new \RuntimeException($message);
        };

        $recorder->recordException(
            $throw('Something exploded for user 0193a1b2-c3d4-7e8f-9a0b-1c2d3e4f5a6b'),
        );
        $recorder->recordException(
            $throw('Something exploded for user 0193ffff-0000-7000-8000-000000000001'),
        );

        $this->assertDatabaseCount('error_logs', 1);

        $row = ErrorLog::query()->firstOrFail();
        $this->assertSame(2, $row->occurrence_count);
        $this->assertSame(ErrorLog::TYPE_EXCEPTION, $row->type);
        $this->assertSame(ErrorLog::STATUS_OPEN, $row->status);
        $this->assertSame(\RuntimeException::class, $row->exception_class);
    }

    public function test_log_error_creates_log_type_row(): void
    {
        Log::error('Payment webhook signature invalid', ['gateway' => 'midtrans']);

        $this->assertDatabaseCount('error_logs', 1);

        $row = ErrorLog::query()->firstOrFail();
        $this->assertSame(ErrorLog::TYPE_LOG, $row->type);
        $this->assertSame('error', $row->level);
        $this->assertStringContainsString('Payment webhook', $row->message);
    }

    public function test_business_rule_exception_is_not_stored(): void
    {
        $e = new class('Nope') extends BusinessRuleException
        {
            public function statusCode(): int
            {
                return 422;
            }
        };

        app(ErrorLogRecorder::class)->recordException($e);

        $this->assertDatabaseCount('error_logs', 0);
    }

    public function test_kill_switch_disables_persistence(): void
    {
        config(['errorlog.enabled' => false]);

        app(ErrorLogRecorder::class)->recordException(new \RuntimeException('should not store'));
        Log::error('should not store either');

        $this->assertDatabaseCount('error_logs', 0);
    }

    public function test_log_with_exception_context_is_not_double_counted(): void
    {
        $e = new \RuntimeException('dual path');

        app(ErrorLogRecorder::class)->recordException($e);
        app(ErrorLogRecorder::class)->recordLog('error', 'dual path', ['exception' => $e]);

        $this->assertDatabaseCount('error_logs', 1);
        $this->assertSame(1, ErrorLog::query()->firstOrFail()->occurrence_count);
    }

    public function test_resolved_group_reopens_on_new_occurrence(): void
    {
        $recorder = app(ErrorLogRecorder::class);
        $throw = static fn (): \RuntimeException => new \RuntimeException('flaky');

        $recorder->recordException($throw());

        $row = ErrorLog::query()->firstOrFail();
        $row->update([
            'status' => ErrorLog::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);

        $recorder->recordException($throw());

        $row->refresh();
        $this->assertSame(ErrorLog::STATUS_OPEN, $row->status);
        $this->assertSame(2, $row->occurrence_count);
        $this->assertNull($row->resolved_at);
    }
}
