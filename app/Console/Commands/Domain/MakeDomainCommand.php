<?php

namespace App\Console\Commands\Domain;

use App\Support\Domain\DomainManager;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class MakeDomainCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:make
        {name : Domain name in StudlyCase or kebab-case (e.g. Billing, activity-log)}';

    /** @var string */
    protected $description = 'Scaffold a new removable domain module under app/Domain/*';

    public function handle(DomainManager $domains): int
    {
        try {
            $name = $domains->normalizeName((string) $this->argument('name'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Creating domain [{$name}]…");
        $this->newLine();

        try {
            $domains->scaffold($name);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Failed to scaffold domain: '.$e->getMessage());

            return self::FAILURE;
        }

        $slug = $domains->slug($name);

        $this->components->info("Domain [{$name}] created (removable).");
        $this->line('Created:');
        $this->line("  - <info>app/Domain/{$name}/</info>");
        $this->line("  - <info>app/Providers/Domain/{$name}ServiceProvider.php</info>");
        $this->line("  - <info>routes/api/v1/{$slug}.php</info>");
        $this->line("  - <info>routes/web/{$slug}.php</info>");
        $this->line("  - <info>tests/Domain/{$name}/Feature/</info>");
        $this->line('Registered in config/domains.php, bootstrap/providers.php, routes/api/v1.php, routes/web.php.');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Add controllers, services, models under the domain folder.');
        $this->line('  2. Define routes in the new route files.');
        $this->line('  3. If public URLs or JSON shapes change, update Gateway/Frontend in the same task.');
        $this->line('  4. Run <info>composer test</info>.');

        return self::SUCCESS;
    }
}
