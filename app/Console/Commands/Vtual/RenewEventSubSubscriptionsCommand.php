<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Services\TwitchEventSubSubscriptionService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'vtual:renew-eventsub-subscriptions')]
class RenewEventSubSubscriptionsCommand extends Command
{
    protected $signature = 'vtual:renew-eventsub-subscriptions
                            {--repair-missing : Subscribe active Twitch channels without EventSub rows}';

    protected $description = 'Renew Twitch EventSub subscriptions nearing lease expiry';

    public function handle(TwitchEventSubSubscriptionService $eventsub): int
    {
        $renewed = $eventsub->renewDueSubscriptions();
        $repaired = 0;

        if ((bool) $this->option('repair-missing')) {
            $repaired = $eventsub->subscribeMissingActiveChannels();
        }

        $this->info("EventSub renew: {$renewed} renewed, {$repaired} repaired.");

        return self::SUCCESS;
    }
}
