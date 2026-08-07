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
                            {--limit=50 : Max items (capped at config batch)}
                            {--video=* : YouTube video ID(s) to fetch directly}
                            {--discover-live : Search current live broadcasts for this channel}';

    protected $description = 'Fetch YouTube channel content and upsert into vtual_contents (POC)';

    public function handle(ChannelContentFetchService $fetch): int
    {
        $channelId = (string) $this->argument('channel');
        $channel = Channel::query()->whereKey($channelId)->first();

        if ($channel === null) {
            $this->error("Channel not found: {$channelId}");

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $discoverLive = (bool) $this->option('discover-live');
        $videoIds = collect($this->option('video'))
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values();

        try {
            if ($discoverLive) {
                $videoIds = $videoIds
                    ->merge($fetch->discoverLiveVideoIds($channel))
                    ->unique()
                    ->values();
            }

            $videoOnly = $videoIds->isNotEmpty() && ! $discoverLive;

            if ($videoOnly) {
                $summary = $fetch->fetchVideosAndPersist($channel, $videoIds->all());
                $this->info(sprintf(
                    'Channel %s: requested=%d fetched=%d upserted=%d',
                    $channelId,
                    $summary['requested'],
                    $summary['fetched'],
                    $summary['upserted'],
                ));

                return self::SUCCESS;
            }

            $summary = $fetch->fetchAndPersist($channel, $limit);

            if ($videoIds->isNotEmpty()) {
                $byVideo = $fetch->fetchVideosAndPersist($channel, $videoIds->all());
                $summary['fetched'] += $byVideo['fetched'];
                $summary['upserted'] += $byVideo['upserted'];
            }

            $this->info(sprintf(
                'Channel %s: listed=%d fetched=%d upserted=%d',
                $channelId,
                $summary['listed'],
                $summary['fetched'],
                $summary['upserted'],
            ));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
