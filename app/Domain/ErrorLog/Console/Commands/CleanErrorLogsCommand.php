<?php

namespace App\Domain\ErrorLog\Console\Commands;

use App\Domain\ErrorLog\Services\ErrorLogService;
use Illuminate\Console\Command;

class CleanErrorLogsCommand extends Command
{
    protected $signature = 'errorlog:clean
                            {--days= : Override ERRORLOG_CLEAN_AFTER_DAYS}';

    protected $description = 'Delete persistent error log groups older than the retention window';

    public function handle(ErrorLogService $errorLogs): int
    {
        $days = $this->option('days');
        $days = $days !== null && $days !== ''
            ? (int) $days
            : (int) config('errorlog.clean_after_days', 90);

        $deleted = $errorLogs->clean($days);

        $this->info("Deleted {$deleted} error log group(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
