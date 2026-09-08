<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Enums\ChannelFetchPoolStatus;
use App\Domain\Vtual\Enums\ContentFetchPoolStatus;
use App\Domain\Vtual\Models\ChannelFetchPoolItem;
use App\Domain\Vtual\Models\ContentFetchPoolItem;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'vtual:prune-fetch-pools')]
class PruneFetchPoolsCommand extends Command
{
    protected $signature = 'vtual:prune-fetch-pools
                            {--days= : Retention window in days (default config vtual.pool_retention.days)}';

    protected $description = 'Prune processed vTual fetch pool rows (done/failed) older than the retention window';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = is_numeric($daysOption)
            ? max(1, (int) $daysOption)
            : max(1, (int) setting_config('vtual.pool_retention.days', 7));

        $cutoff = now()->subDays($days);

        $channelDeleted = $this->pruneModel(
            ChannelFetchPoolItem::class,
            [ChannelFetchPoolStatus::Done->value, ChannelFetchPoolStatus::Failed->value],
            $cutoff,
        );

        $contentDeleted = $this->pruneModel(
            ContentFetchPoolItem::class,
            [ContentFetchPoolStatus::Done->value, ContentFetchPoolStatus::Failed->value],
            $cutoff,
        );

        $this->line(sprintf(
            'Pruned fetch pool rows older than %d days (%s): channel=%d content=%d',
            $days,
            $cutoff->toDateTimeString(),
            $channelDeleted,
            $contentDeleted,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  list<string>  $statuses
     */
    private function pruneModel(string $model, array $statuses, \Illuminate\Support\Carbon $cutoff): int
    {
        $deleted = 0;

        do {
            $batch = $model::query()
                ->whereIn('status', $statuses)
                ->whereNotNull('processed_at')
                ->where('processed_at', '<', $cutoff)
                ->limit(500)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
