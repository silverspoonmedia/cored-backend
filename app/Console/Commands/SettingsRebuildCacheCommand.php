<?php

namespace App\Console\Commands;

use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;
use Illuminate\Console\Command;

class SettingsRebuildCacheCommand extends Command
{
    protected $signature = 'settings:rebuild-cache';

    protected $description = 'Rebuild the forever cache snapshot for system_settings from the database';

    public function handle(SystemSettingRepositoryInterface $repository): int
    {
        $snapshot = $repository->rebuildCache();

        $this->info('Settings cache rebuilt ('.count($snapshot).' keys).');

        return self::SUCCESS;
    }
}
