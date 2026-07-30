<?php

namespace App\Console\Commands;

use App\Support\EnvironmentBackupManager;
use App\Support\EnvironmentProfileManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'env:backup:create')]
class EnvBackupCreateCommand extends Command
{
    protected $signature = 'env:backup:create
        {--include-snapshot : Also back up the active profile snapshot}';

    protected $description = 'Create a pending transactional backup of .env (and optional snapshot) without mutating files';

    public function __construct(
        private readonly EnvironmentBackupManager $backups,
        private readonly EnvironmentProfileManager $profiles,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->backups->hasPending()) {
            $pending = $this->backups->latestPending();
            $this->error('A pending env backup already exists ['.($pending?->id ?? 'unknown').'].');
            $this->line('Run `php artisan env:restore --discard` first.');

            return self::FAILURE;
        }

        $paths = [];

        if (File::exists($this->profiles->envPath())) {
            $paths[$this->profiles->envPath()] = '.env';
        }

        if ($this->option('include-snapshot')) {
            $profile = $this->profiles->activeProfile();

            if ($profile !== null && $this->profiles->snapshotExists($profile)) {
                $paths[$this->profiles->profilePath($profile)] = '.env.'.$profile;
            }
        }

        if ($paths === []) {
            $this->error('No env files found to back up.');

            return self::FAILURE;
        }

        $backup = $this->backups->createPending('env:backup:create', $paths);

        $this->info('Created pending backup ['.$backup->id.'].');
        $this->line('Directory: '.$backup->directory);
        $this->line('Run `php artisan env:restore --discard` after your manual edits succeed.');

        return self::SUCCESS;
    }
}
