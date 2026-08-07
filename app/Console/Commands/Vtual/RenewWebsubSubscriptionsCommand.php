<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Services\YoutubeWebSubSubscriptionService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'vtual:renew-websub-subscriptions')]
class RenewWebsubSubscriptionsCommand extends Command
{
    protected $signature = 'vtual:renew-websub-subscriptions
                            {--repair-missing : Subscribe active YouTube channels without a WebSub row}';

    protected $description = 'Renew YouTube WebSub subscriptions nearing lease expiry';

    public function handle(YoutubeWebSubSubscriptionService $websub): int
    {
        $renewed = $websub->renewDueSubscriptions();
        $repaired = 0;

        if ((bool) $this->option('repair-missing')) {
            $repaired = $websub->subscribeMissingActiveChannels();
        }

        $this->info("WebSub renew: {$renewed} renewed, {$repaired} repaired.");

        return self::SUCCESS;
    }
}
