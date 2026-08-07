<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Services\CreatorClaimVerifyPoolService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'vtual:flush-claim-verify-pool')]
class FlushClaimVerifyPoolCommand extends Command
{
    protected $signature = 'vtual:flush-claim-verify-pool
                            {--force : Ignore size/age gate; flush up to batch max}';

    protected $description = 'Flush vTual creator claim verify pool (queued bio verification)';

    public function handle(CreatorClaimVerifyPoolService $pool): int
    {
        $force = (bool) $this->option('force');
        $summary = $pool->flush($force);

        $this->line(sprintf(
            'status=%s claimed=%d verified=%d failed=%d http=%d',
            $summary['status'],
            $summary['claimed'],
            $summary['verified'],
            $summary['failed'],
            $summary['http'],
        ));

        return self::SUCCESS;
    }
}
