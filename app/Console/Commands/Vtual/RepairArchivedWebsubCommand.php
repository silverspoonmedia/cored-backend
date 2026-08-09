<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Services\YoutubeWebSubSubscriptionService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'vtual:repair-archived-websub')]
class RepairArchivedWebsubCommand extends Command
{
    protected $signature = 'vtual:repair-archived-websub
                            {--limit=50 : Max archived channels to repair per run}';

    protected $description = 'Re-subscribe archived YouTube channels missing active WebSub (legacy pre-policy repair)';

    public function handle(YoutubeWebSubSubscriptionService $websub): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $repaired = $websub->subscribeMissingArchivedChannels($limit);

        $this->info("Archived WebSub repair: {$repaired} channel(s) subscribed.");

        return self::SUCCESS;
    }
}
