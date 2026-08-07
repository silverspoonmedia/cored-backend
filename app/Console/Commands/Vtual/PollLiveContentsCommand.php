<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Services\LiveContentPollService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'vtual:poll-live-contents')]
class PollLiveContentsCommand extends Command
{
    protected $description = 'Poll API for live contents (CCV snapshot + ended detection)';

    protected $signature = 'vtual:poll-live-contents';

    public function handle(LiveContentPollService $poll): int
    {
        try {
            $summary = $poll->poll();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Live poll: polled=%d updated=%d ended=%d',
            $summary['polled'],
            $summary['updated'],
            $summary['ended'],
        ));

        return self::SUCCESS;
    }
}
