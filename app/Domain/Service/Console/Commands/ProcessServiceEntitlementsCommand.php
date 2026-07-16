<?php

namespace App\Domain\Service\Console\Commands;

use App\Domain\Service\Services\ServiceEntitlementLifecycleService;
use Illuminate\Console\Command;

class ProcessServiceEntitlementsCommand extends Command
{
    protected $signature = 'service:process-entitlements';

    protected $description = 'Enter grace for ended subscriptions; expire grace and purge free-tier excess storage';

    public function handle(ServiceEntitlementLifecycleService $lifecycle): int
    {
        $grace = $lifecycle->enterGraceForDue();
        $expired = $lifecycle->expireGraceDue();

        $this->info("Entered grace: {$grace}; expired + purged users: {$expired}");

        return self::SUCCESS;
    }
}
