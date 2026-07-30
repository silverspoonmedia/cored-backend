<?php

namespace App\Console\Commands;

use App\Support\EnvironmentBackupManager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'env:restore')]
class EnvRestoreCommand extends Command
{
    protected $signature = 'env:restore
        {id? : Pending backup id (default: latest)}
        {--list : List pending backups}
        {--discard : Delete pending backup without restoring files}';

    protected $description = 'Restore or manage pending transactional env backups';

    public function __construct(
        private readonly EnvironmentBackupManager $backups,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->displayList();
        }

        $pending = $this->resolveBackup();

        if ($pending === null) {
            $this->info('No pending env backups.');

            return self::SUCCESS;
        }

        if ($this->option('discard')) {
            $this->backups->discard($pending);
            $this->info("Discarded pending backup [{$pending->id}].");

            return self::SUCCESS;
        }

        $this->backups->rollback($pending);
        $this->info("Restored files from pending backup [{$pending->id}] ({$pending->operation}).");
        $this->line('Backup kept at: '.$pending->directory);
        $this->line('Run `php artisan env:restore --discard` when you no longer need this backup.');

        return self::SUCCESS;
    }

    private function displayList(): int
    {
        $pending = $this->backups->listPending();

        if ($pending === []) {
            $this->info('No pending env backups.');

            return self::SUCCESS;
        }

        $rows = array_map(
            fn ($backup): array => [
                $backup->id,
                $backup->operation,
                date('Y-m-d H:i:s', $backup->createdAt),
                count($backup->files),
                $backup->directory,
            ],
            $pending,
        );

        $this->table(['ID', 'Operation', 'Created', 'Files', 'Directory'], $rows);

        return self::SUCCESS;
    }

    private function resolveBackup(): ?\App\Support\PendingBackup
    {
        $id = $this->argument('id');

        if ($id !== null) {
            return $this->backups->findById((string) $id);
        }

        return $this->backups->latestPending();
    }
}
