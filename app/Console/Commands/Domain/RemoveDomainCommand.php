<?php

namespace App\Console\Commands\Domain;

use App\Support\Domain\DomainManager;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class RemoveDomainCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:remove
        {name : Domain name to remove (must be unlocked product; core always refused)}
        {--force : Skip confirmation prompt}';

    /** @var string */
    protected $description = 'Remove an unlocked product domain module (refuses core and locked product domains)';

    public function handle(DomainManager $domains): int
    {
        try {
            $name = $domains->normalizeName((string) $this->argument('name'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($domains->isPlatformCore($name) || $domains->isCore($name)) {
            $this->error(
                "Domain [{$name}] is core and cannot be removed. Config cannot override platform core."
            );

            return self::FAILURE;
        }

        if ($domains->isLocked($name)) {
            $this->error(
                "Product domain [{$name}] is locked. Set 'locked' => false in config/domains.php manually, then re-run domain:remove."
            );

            return self::FAILURE;
        }

        if (! $domains->isRegistered($name) && ! $domains->directoryExists($name)) {
            $this->error("Domain [{$name}] is not registered and has no app/Domain/{$name} directory.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Remove domain [{$name}]? This deletes PHP/route/test files (migrations are kept).", false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->info("Removing domain [{$name}]…");

        try {
            $warnings = $domains->remove($name);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Failed to remove domain: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Domain [{$name}] removed.");

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
