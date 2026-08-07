<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Services\ChannelFeedPollService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'vtual:poll-channel-feeds')]
class PollChannelFeedsCommand extends Command
{
    protected $signature = 'vtual:poll-channel-feeds
                            {--channel= : Limit to one vtual_channels UUID}
                            {--force : Poll even if interval not elapsed}';

    protected $description = 'Poll YouTube public Atom feeds and enqueue new video ids for API hydrate';

    public function handle(ChannelFeedPollService $poll): int
    {
        $channelId = $this->option('channel');
        $onlyChannelId = is_string($channelId) && $channelId !== '' ? $channelId : null;
        $force = (bool) $this->option('force');

        try {
            $summary = $poll->pollDueChannels($onlyChannelId, $force);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Feed poll: channels=%d enqueued=%d skipped=%d',
            $summary['channels'],
            $summary['enqueued'],
            $summary['skipped'],
        ));

        return self::SUCCESS;
    }
}
