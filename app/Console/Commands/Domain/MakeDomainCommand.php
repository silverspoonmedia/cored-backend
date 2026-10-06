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
        {name : Domain name in StudlyCase or kebab-case (e.g. Billing, activity-log)}
        {--locked : Create as locked product against removal (default)}
        {--removable : Create as removable product (overrides --locked)}
        {--parent= : Parent product domain name (scaffolds a subproduct)}';

    /** @var string */
    protected $description = 'Scaffold a new product domain module under app/Domain/* (locked by default)';

    public function handle(DomainManager $domains): int
    {
        try {
            $name = $domains->normalizeName((string) $this->argument('name'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $locked = $this->resolveLocked();

        try {
            $parent = $this->resolveParent($domains);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $lifecycle = $locked ? 'locked' : 'removable';
        $this->info("Creating domain [{$name}] (product, {$lifecycle})…");
        $this->newLine();

        try {
            $domains->scaffold($name, locked: $locked, parent: $parent);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Failed to scaffold domain: '.$e->getMessage());

            return self::FAILURE;
        }

        $slug = $domains->slug($name);

        $this->components->info("Domain [{$name}] created (product, {$lifecycle}).");

        if ($parent !== null) {
            $this->line("Parent: <info>{$parent}</info> (subproduct — remove the parent only after this domain).");
        }

        $this->line('Created:');
        $this->line("  - <info>app/Domain/{$name}/</info>");
        $this->line("  - <info>app/Providers/Domain/{$name}ServiceProvider.php</info>");
        $this->line("  - <info>routes/api/v1/{$slug}.php</info>");
        $this->line("  - <info>routes/web/{$slug}.php</info>");
        $this->line("  - <info>database/seeders/{$name}RoleSeeder.php</info> (Beta tester role + Public visibility)");
        $this->line("  - <info>tests/Domain/{$name}/Feature/</info>");
        $this->line("  - <info>app/Domain/{$name}/uninstall.json</info> (removal mapping for domain:remove)");
        $this->line('Registered in config/domains.php, bootstrap/providers.php, routes/api/v1.php, routes/web.php, database/seeders/DatabaseSeeder.php.');

        if ($locked) {
            $this->newLine();
            $this->line('This product domain is <comment>locked</comment>. To remove later: set <info>\'locked\' => false</info> in <info>config/domains.php</info>, then run <info>domain:remove</info>.');
        }

        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Add controllers, services, models under the domain folder.');
        $this->line('  2. Define routes in the new route files. Gate them with <info>domain.access:'.$name.'</info> + <info>product.active:'.$name.'</info>.');
        $this->line("  3. Run <info>php artisan db:seed --class={$name}RoleSeeder</info> to create the Beta tester role + visibility row.");
        $this->line('  4. If public URLs or JSON shapes change, update the matching living docs in the same task.');
        $this->line('  5. Run <info>composer test</info>.');

        return self::SUCCESS;
    }

    /**
     * Default locked. --removable wins over --locked. Interactive prompt only when neither flag is set.
     */
    private function resolveLocked(): bool
    {
        if ($this->option('removable')) {
            return false;
        }

        if ($this->option('locked')) {
            return true;
        }

        if ($this->input->isInteractive()) {
            return $this->confirm('Lock against removal? (recommended for product domains)', true);
        }

        return true;
    }

    /**
     * Composition axis — an optional registered product parent (subproduct).
     * A subproduct is still a full product; only its removal order changes.
     *
     * @throws InvalidArgumentException when the parent is not a registered product domain
     */
    private function resolveParent(DomainManager $domains): ?string
    {
        $raw = $this->option('parent');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $parent = $domains->normalizeName($raw);

        if (! $domains->isRegistered($parent) || ! $domains->isProduct($parent)) {
            throw new InvalidArgumentException(
                "Parent [{$parent}] is not a registered product domain. Register it first, or drop --parent."
            );
        }

        return $parent;
    }
}
