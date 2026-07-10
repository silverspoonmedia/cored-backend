<?php

namespace App\Console\Commands\Domain;

use App\Support\Domain\DomainManager;
use Illuminate\Console\Command;

class ListDomainsCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:list';

    /** @var string */
    protected $description = 'List registered domain modules (core / locked / removable)';

    public function handle(DomainManager $domains): int
    {
        $rows = [];

        foreach ($domains->all() as $name => $meta) {
            $rows[] = [
                $name,
                $domains->kind($name),
                $domains->directoryExists($name) ? 'yes' : 'missing',
                $domains->slug($name),
            ];
        }

        if ($rows === []) {
            $this->warn('No domains registered in config/domains.php.');

            return self::SUCCESS;
        }

        $this->table(['Domain', 'Kind', 'app/Domain', 'Slug'], $rows);
        $this->line('Kinds: <info>core</info> = platform (never removable); <info>locked</info> = unlock via config/domains.php; <info>removable</info> = domain:remove allowed.');

        return self::SUCCESS;
    }
}
