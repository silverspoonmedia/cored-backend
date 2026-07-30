<?php

namespace App\Console\Commands;

use App\Support\EnvironmentCurator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'env:curate')]
class EnvCurateCommand extends Command
{
    protected $signature = 'env:curate
        {--dry-run : Show what would change without writing}
        {--sync-snapshot : Also update the active profile snapshot after curating}
        {--force-past-backup : Proceed even if a pending backup exists}';

    protected $description = 'Reformat .env using the active profile template layout while preserving values';

    public function __construct(
        private readonly EnvironmentCurator $curator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $this->curator->curate(
                dryRun: $dryRun,
                syncSnapshot: (bool) $this->option('sync-snapshot'),
                forcePastBackup: (bool) $this->option('force-past-backup'),
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Keys added from template: '.count($result['added_keys']));
        $this->line('Orphan keys preserved: '.count($result['orphan_keys']));
        $this->line('Lines: '.$result['lines_before'].' → '.$result['lines_after']);

        if ($result['added_keys'] !== []) {
            $this->line('Added: '.implode(', ', $result['added_keys']));
        }

        if ($result['orphan_keys'] !== []) {
            $this->line('Orphans: '.implode(', ', $result['orphan_keys']));
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run — no files were modified. Run without --dry-run to apply.');

            return self::SUCCESS;
        }

        if ($result['changed']) {
            $this->info('Curated .env successfully.');
        } else {
            $this->info('No layout changes needed.');
        }

        if ($this->option('sync-snapshot')) {
            $this->line('Active profile snapshot updated.');
        }

        return self::SUCCESS;
    }
}
