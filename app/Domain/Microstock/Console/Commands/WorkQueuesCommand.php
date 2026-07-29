<?php

namespace App\Domain\Microstock\Console\Commands;

use App\Domain\Microstock\Support\WorkerQueues;
use Illuminate\Console\Command;

class WorkQueuesCommand extends Command
{
    protected $signature = 'microstock:queue-work
                            {--listen : Use queue:listen instead of queue:work}
                            {--tries=3 : Job attempts before moving to failed_jobs}
                            {--timeout=0 : Seconds a child worker may run}
                            {--once : Process a single job and exit}
                            {--stop-when-empty : Stop when the queue is empty}';

    protected $description = 'Run a queue worker for all Microstock queues (paid, free, and default orchestration)';

    public function handle(): int
    {
        $queues = WorkerQueues::csv();
        $listen = (bool) $this->option('listen');
        $command = $listen ? 'queue:listen' : 'queue:work';

        $this->info("Microstock worker ({$command}): {$queues}");

        $options = [
            '--queue' => $queues,
            '--tries' => $this->option('tries'),
            '--timeout' => $this->option('timeout'),
        ];

        if ($this->option('once')) {
            $options['--once'] = true;
        }

        if ($this->option('stop-when-empty')) {
            $options['--stop-when-empty'] = true;
        }

        return $this->call($command, $options);
    }
}
