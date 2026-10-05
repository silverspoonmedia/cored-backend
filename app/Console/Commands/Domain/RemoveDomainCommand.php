<?php

namespace App\Console\Commands\Domain;

use App\Support\Database\DestructiveDatabaseCommandGuard;
use App\Support\Domain\DomainManager;
use App\Support\Domain\UninstallManifest;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class RemoveDomainCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:remove
        {name : Domain name to remove (must be unlocked product; core always refused)}
        {--dry-run : Print the uninstall plan and unlisted references, change nothing}
        {--draft-manifest : Write a candidate uninstall.json from the reference scan, then stop}
        {--purge-data : Also drop the recorded tables, rows, RBAC and migration files}
        {--force : Skip the confirmation prompt (required when uninstall.json has unresolved entries)}';

    /** @var string */
    protected $description = 'Remove an unlocked product domain module using its uninstall.json manifest';

    public function handle(DomainManager $domains, UninstallManifest $manifest, DestructiveDatabaseCommandGuard $guard): int
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

        if ($this->option('draft-manifest')) {
            return $this->draftManifest($manifest, $name);
        }

        if (! $domains->isRegistered($name) && ! $domains->directoryExists($name)) {
            $this->error("Domain [{$name}] is not registered and has no app/Domain/{$name} directory.");

            return self::FAILURE;
        }

        if (! $manifest->exists($name)) {
            $this->warn(
                "No app/Domain/{$name}/uninstall.json — falling back to the structural removal only "
                .'(PHP/routes/tests/provider). Shared files, SPA screens, docs and data will be left behind.'
            );
            $this->line('Run <info>php artisan domain:remove '.$name.' --draft-manifest</info> to record them.');

            if ($this->option('purge-data')) {
                $this->error('--purge-data needs the manifest: it is what names the tables, rows and RBAC to purge.');

                return self::FAILURE;
            }
        }

        try {
            $plan = $manifest->exists($name) ? $manifest->plan($name) : null;
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($plan !== null) {
            $this->renderPlan($name, $plan, (bool) $this->option('dry-run'));
        }

        if ($domains->isLocked($name)) {
            if (! $this->option('dry-run')) {
                $this->error(
                    "Product domain [{$name}] is locked. Set 'locked' => false in config/domains.php manually, then re-run domain:remove."
                );

                return self::FAILURE;
            }

            $this->newLine();
            $this->components->warn(
                "Product domain [{$name}] is locked — a real removal needs 'locked' => false in config/domains.php."
            );
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->components->info('Dry run — nothing was changed.');

            return self::SUCCESS;
        }

        // Checked before anything is deleted: a blocked purge must not leave the
        // domain half-removed.
        if ($this->option('purge-data')) {
            try {
                $guard->assertPurgeAllowed();
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        if ($plan !== null && $plan['unresolved'] !== [] && ! $this->option('force')) {
            $this->newLine();
            $this->error('uninstall.json still has unresolved entries — finish the manifest first:');

            foreach ($plan['unresolved'] as $entry) {
                $this->line('  - '.$entry);
            }

            $this->line('Pass <info>--force</info> only if you have verified those files by hand.');

            return self::FAILURE;
        }

        $prompt = $this->option('purge-data')
            ? "Remove domain [{$name}]? This deletes PHP/route/test files, the recorded shared-file edits, and PURGES the recorded tables and rows."
            : "Remove domain [{$name}]? This deletes PHP/route/test files and the recorded shared-file edits (migrations are kept).";

        if (! $this->option('force') && ! $this->confirm($prompt, false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->info("Removing domain [{$name}]…");

        try {
            if ($plan !== null) {
                $files = $manifest->applyFiles($name, $plan);

                foreach ($files['warnings'] as $warning) {
                    $this->warn($warning);
                }

                $this->components->info(
                    "Manifest applied: {$files['deleted']} path(s) deleted, {$files['edited']} file edit(s), {$files['removed']} line/entry removals."
                );
            }

            $warnings = $domains->remove($name);

            foreach ($warnings as $warning) {
                $this->warn($warning);
            }

            if ($this->option('purge-data')) {
                // The guard already passed before any file was touched.
                foreach ($manifest->purgeData($name, $plan['data'] ?? []) as $warning) {
                    $this->warn($warning);
                }

                $this->components->info('Recorded data purged (tables, rows, RBAC, migrations).');
            } elseif (($plan['data']['tables'] ?? []) !== []) {
                $this->warn('Tables kept: '.implode(', ', $plan['data']['tables']).'. Re-run with --purge-data to drop them.');
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Failed to remove domain: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->flushCaches();

        $this->components->info("Domain [{$name}] removed.");

        return self::SUCCESS;
    }

    private function draftManifest(UninstallManifest $manifest, string $name): int
    {
        if ($manifest->exists($name)) {
            $this->error(
                "app/Domain/{$name}/uninstall.json already exists. Review it, or delete it to re-draft."
            );

            return self::FAILURE;
        }

        try {
            $path = $manifest->writeDraft($name);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $draft = json_decode((string) file_get_contents($path), true);

        $this->components->info("Draft written to {$path}");
        $this->newLine();

        foreach (($draft['repos'] ?? []) as $repo => $section) {
            $this->line(sprintf(
                '  %-8s %d delete · %d edits · <comment>%d unresolved</comment>',
                $repo,
                count($section['delete'] ?? []),
                count($section['edits'] ?? []),
                count($section['unresolved'] ?? []),
            ));
        }

        $unresolved = [];

        foreach (($draft['repos'] ?? []) as $repo => $section) {
            foreach (($section['unresolved'] ?? []) as $entry) {
                $unresolved[] = $repo.':'.($entry['file'] ?? $entry);
            }
        }

        if ($unresolved !== []) {
            $this->newLine();
            $this->warn('Resolve these by hand — the drift guard fails while any remain:');

            foreach ($unresolved as $entry) {
                $this->line('  - '.$entry);
            }
        }

        $this->newLine();
        $this->line('Then run <info>php artisan domain:remove '.$name.' --dry-run</info> until it is clean.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function renderPlan(string $name, array $plan, bool $detailed): void
    {
        $this->newLine();
        $this->line("Uninstall plan for <info>{$name}</info>");

        $byRepo = [];

        foreach ($plan['deletes'] as $entry) {
            $byRepo[$entry['repo']]['delete'] = ($byRepo[$entry['repo']]['delete'] ?? 0) + count($entry['matches']);
        }

        foreach ($plan['edits'] as $entry) {
            $byRepo[$entry['repo']]['edit_files'] = ($byRepo[$entry['repo']]['edit_files'] ?? 0) + count($entry['matches']);
            $byRepo[$entry['repo']]['edit_lines'] = ($byRepo[$entry['repo']]['edit_lines'] ?? 0) + $entry['removed'];
        }

        foreach ($byRepo as $repo => $counts) {
            $this->line(sprintf(
                '  %-8s %d delete · %d file edit(s) removing %d line(s)/entr(ies)',
                $repo,
                $counts['delete'] ?? 0,
                $counts['edit_files'] ?? 0,
                $counts['edit_lines'] ?? 0,
            ));
        }

        $data = $plan['data'] ?? [];
        $this->line(sprintf(
            '  %-8s %d table(s) · %d migration(s) · %d permission(s) · %d role(s) · %d row filter(s)',
            'data',
            count($data['tables'] ?? []),
            count($data['migrations'] ?? []),
            count($data['rbac']['permissions'] ?? []),
            count($data['rbac']['roles'] ?? []),
            count($data['rows'] ?? []),
        ));

        if ($detailed) {
            $this->newLine();

            foreach ($plan['deletes'] as $entry) {
                foreach ($entry['matches'] as $absolute) {
                    $this->line('  <comment>delete</comment> '.$entry['repo'].':'.$entry['path']);
                }
            }

            foreach ($plan['edits'] as $entry) {
                $this->line(sprintf(
                    '  <comment>edit  </comment> %s:%s <info>[%s]</info>',
                    $entry['repo'],
                    $entry['file'],
                    $entry['op'],
                ));
            }
        }

        foreach ($plan['warnings'] as $warning) {
            $this->warn($warning);
        }

        if ($plan['unresolved'] !== []) {
            $this->newLine();
            $this->warn('unresolved entries (no op recorded yet):');

            foreach ($plan['unresolved'] as $entry) {
                $this->line('  - '.$entry);
            }
        }

        $unlisted = $plan['unlisted'];

        if ($unlisted !== []) {
            $this->newLine();
            $this->warn('references not covered by the manifest:');

            foreach ($unlisted as $repo => $files) {
                foreach ($files as $relative => $lines) {
                    $this->line(sprintf('  - %s:%s (%d hit(s))', $repo, $relative, count($lines)));
                }
            }
        }
    }

    private function flushCaches(): void
    {
        foreach (['menu:flush-cache', 'permission:cache-reset'] as $command) {
            try {
                $this->call($command);
            } catch (Throwable $e) {
                $this->warn("Could not run {$command}: ".$e->getMessage());
            }
        }
    }
}
