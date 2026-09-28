<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Models\ChannelMetric;
use App\Domain\Vtual\Models\ContentMetric;
use App\Domain\Vtual\Models\ContentStatusEvent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'vtual:prune-metrics')]
class PruneMetricsCommand extends Command
{
    protected $signature = 'vtual:prune-metrics
                            {--days= : Retention window in days (default config vtual.metrics.retention_days)}';

    protected $description = 'Prune historical vTual metric snapshots and content status events older than the retention window';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = is_numeric($daysOption)
            ? max(1, (int) $daysOption)
            : max(1, (int) setting_config('vtual.metrics.retention_days', 90));

        $cutoff = now()->subDays($days);

        $channelDeleted = $this->pruneModel(ChannelMetric::class, $cutoff);
        $contentDeleted = $this->pruneModel(ContentMetric::class, $cutoff);

        // The status timeline shares the window on purpose: a transition with no
        // samples left to annotate it is not a report anyone can read.
        $statusDeleted = $this->pruneModel(ContentStatusEvent::class, $cutoff, 'occurred_at');

        $this->line(sprintf(
            'Pruned history older than %d days (%s): channel=%d content=%d status=%d',
            $days,
            $cutoff->toDateTimeString(),
            $channelDeleted,
            $contentDeleted,
            $statusDeleted,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function pruneModel(string $model, Carbon $cutoff, string $column = 'captured_at'): int
    {
        $deleted = 0;

        do {
            $batch = $model::query()
                ->where($column, '<', $cutoff)
                ->limit(500)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
