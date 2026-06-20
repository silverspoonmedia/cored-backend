<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\MenuRepositoryInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('menu:flush-cache')]
#[Description('Flush cached navigation menu snapshot')]
class FlushMenuCache extends Command
{
    public function handle(MenuRepositoryInterface $menus): int
    {
        $menus->flushNavigationCache();

        $this->info('Navigation menu cache flushed.');

        return self::SUCCESS;
    }
}
