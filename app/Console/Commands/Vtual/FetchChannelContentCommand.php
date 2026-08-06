<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Services\ChannelContentFetchService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'vtual:fetch-channel-content')]
class FetchChannelContentCommand extends Command
{
    protected $signature = 'vtual:fetch-channel-content
                            {channel : vtual_channels UUID}
                            {--limit=50 : Max items (capped at config batch)}';

    protected $description = 'Fetch recent YouTube uploads for a channel and upsert into vtual_contents (POC)';

    public function handle(ChannelContentFetchService $fetch): int
    {
        $channelId = (string) $this->argument('channel');
        $channel = Channel::query()->whereKey($channelId)->first();

        if ($channel === null) {
            $this->error("Channel not found: {$channelId}");

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');

        try {
            $summary = $fetch->fetchAndPersist($channel, $limit);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Channel %s: listed=%d fetched=%d upserted=%d',
            $channelId,
            $summary['listed'],
            $summary['fetched'],
            $summary['upserted'],
        ));

        return self::SUCCESS;
    }
}
