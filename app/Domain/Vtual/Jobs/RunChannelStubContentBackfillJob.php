<?php

namespace App\Domain\Vtual\Jobs;

use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Services\ChannelStubContentBackfillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Async batch API hydrate for stub contents after channel restore.
 */
class RunChannelStubContentBackfillJob implements ShouldQueue
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

    public function handle(ChannelStubContentBackfillService $backfill): void
    {
        $channel = Channel::query()->find($this->channelId);
        if ($channel === null) {
            return;
        }

        $backfill->run($channel);
    }
}
