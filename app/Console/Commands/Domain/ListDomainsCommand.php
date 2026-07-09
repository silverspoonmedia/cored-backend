<?php

namespace App\Console\Commands\Domain;

use App\Support\Domain\DomainManager;
use Illuminate\Console\Command;

class ListDomainsCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:list';

    /** @var string */
    protected $description = 'List registered domain modules (core vs removable)';

    public function handle(DomainManager $domains): int
    {
        $rows = [];

        foreach ($domains->all() as $name => $meta) {
            $rows[] = [
                $name,
                $meta['core'] ? 'core' : 'removable',
                $domains->directoryExists($name) ? 'yes' : 'missing',
                $domains->slug($name),
            ];
        }

        if ($rows === []) {
            $this->warn('No domains registered in config/domains.php.');

            return self::SUCCESS;
        }

        $this->table(['Domain', 'Kind', 'app/Domain', 'Slug'], $rows);
        $this->line('Core domains cannot be removed. Use <info>domain:make</info> / <info>domain:remove</info> for removable modules.');

        return self::SUCCESS;
    }
}
