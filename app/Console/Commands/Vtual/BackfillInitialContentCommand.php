<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Services\ChannelInitialContentBackfillService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'vtual:backfill-initial-content')]
class BackfillInitialContentCommand extends Command
{
    protected $signature = 'vtual:backfill-initial-content
                            {channel : vtual_channels UUID}';

    protected $description = 'Run one-time YouTube content backfill for an existing active channel';

    public function handle(ChannelInitialContentBackfillService $backfill): int
    {
        $channelId = (string) $this->argument('channel');
        $channel = Channel::query()->whereKey($channelId)->first();

        if ($channel === null) {
            $this->error("Channel not found: {$channelId}");

            return self::FAILURE;
        }

        try {
            $summary = $backfill->run($channel);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($summary['status'] === 'completed') {
            $this->info(sprintf(
                'Channel %s: listed=%d fetched=%d upserted=%d',
                $channelId,
                $summary['listed'] ?? 0,
                $summary['fetched'] ?? 0,
                $summary['upserted'] ?? 0,
            ));

            return self::SUCCESS;
        }

        if ($summary['status'] === 'skipped') {
            $this->warn(sprintf(
                'Channel %s: skipped (%s)',
                $channelId,
                $summary['reason'] ?? 'unknown',
            ));

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'Channel %s: failed (%s)',
            $channelId,
            $summary['reason'] ?? 'unknown',
        ));

        return self::FAILURE;
    }
}
