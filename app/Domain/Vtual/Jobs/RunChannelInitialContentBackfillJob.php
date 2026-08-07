<?php

namespace App\Domain\Vtual\Jobs;

use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Services\ChannelInitialContentBackfillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Async one-time YouTube content backfill after channel activation.
 */
class RunChannelInitialContentBackfillJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $channelId,
    ) {}

    public function handle(ChannelInitialContentBackfillService $backfill): void
    {
        $channel = Channel::query()->find($this->channelId);
        if ($channel === null) {
            return;
        }

        $backfill->run($channel);
    }
}
