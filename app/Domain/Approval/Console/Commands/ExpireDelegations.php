<?php

namespace App\Domain\Approval\Console\Commands;

use App\Domain\Approval\Models\ApprovalDelegation;
use Illuminate\Console\Command;

class ExpireDelegations extends Command
{
    protected $signature = 'approval:expire-delegations';

    protected $description = 'Deactivate expired delegations';

    public function handle(): int
    {
        $expired = ApprovalDelegation::where('is_active', true)
            ->where('valid_until', '<', now())
            ->get();

        $expired->each(fn ($d) => $d->update(['is_active' => false]));

        $this->info("Expired {$expired->count()} delegations.");

        return Command::SUCCESS;
    }
}
