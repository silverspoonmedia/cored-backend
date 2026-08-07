<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Services\ContentFetchPoolService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'vtual:flush-content-pool')]
class FlushContentFetchPoolCommand extends Command
{
    protected $signature = 'vtual:flush-content-pool
                            {--platform= : Limit to youtube}
                            {--force : Ignore size/age gate}';

    protected $description = 'Flush vTual content fetch pool (batch videos.list hydrate)';

    public function handle(ContentFetchPoolService $pool): int
    {
        $platformOpt = $this->option('platform');
        $only = null;
        if (is_string($platformOpt) && $platformOpt !== '') {
            try {
                $only = PlatformKey::from(strtolower(trim($platformOpt)));
            } catch (Throwable) {
                $this->error('Invalid --platform. Use youtube.');

                return self::FAILURE;
            }
        }

        $summary = $pool->flush($only, (bool) $this->option('force'));

        foreach ($summary['platforms'] as $key => $row) {
            $this->line(sprintf(
                '[%s] status=%s claimed=%d upserted=%d failed=%d http=%d',
                $key,
                $row['status'],
                $row['claimed'],
                $row['upserted'],
                $row['failed'],
                $row['http'],
            ));
        }

        return self::SUCCESS;
    }
}
