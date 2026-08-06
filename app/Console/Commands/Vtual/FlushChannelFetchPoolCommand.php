<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Services\ChannelFetchPoolService;
use App\Domain\Vtual\Services\ChannelRegistrationFlushService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'vtual:flush-channel-pool')]
class FlushChannelFetchPoolCommand extends Command
{
    protected $signature = 'vtual:flush-channel-pool
                            {--platform= : Limit to youtube|twitch}
                            {--force : Ignore size/age gate; flush up to batch max}
                            {--resync : Enqueue stale active channels first, then flush}';

    protected $description = 'Flush vTual channel fetch pool (batch platform channel snapshots)';

    public function handle(
        ChannelRegistrationFlushService $registration,
        ChannelFetchPoolService $pool,
    ): int {
        $platformOpt = $this->option('platform');
        $only = null;
        if (is_string($platformOpt) && $platformOpt !== '') {
            try {
                $only = PlatformKey::from(strtolower(trim($platformOpt)));
            } catch (Throwable) {
                $this->error('Invalid --platform. Use youtube or twitch.');

                return self::FAILURE;
            }
            if ($only === PlatformKey::Kick) {
                $this->error('Kick is not enabled for channel pool flush.');

                return self::FAILURE;
            }
        }

        $force = (bool) $this->option('force');
        $resync = (bool) $this->option('resync');

        if ($resync) {
            $enqueued = $pool->enqueueStaleActiveChannels($only);
            $this->info("Resync enqueue: {$enqueued} channel(s).");
        }

        $registrationSummary = $registration->flush($only, $force);

        foreach ($registrationSummary['platforms'] as $key => $row) {
            $this->line(sprintf(
                '[%s] registration status=%s claimed=%d applied=%d failed=%d',
                $key,
                $row['status'],
                $row['claimed'],
                $row['applied'],
                $row['failed'],
            ));
        }

        $summary = $pool->flush($only, $force);

        foreach ($summary['platforms'] as $key => $row) {
            $this->line(sprintf(
                '[%s] status=%s claimed=%d applied=%d failed=%d http=%d',
                $key,
                $row['status'],
                $row['claimed'],
                $row['applied'],
                $row['failed'],
                $row['http'],
            ));
        }

        return self::SUCCESS;
    }
}
