<?php

namespace App\Console\Commands;

use App\Support\EnvironmentProfileManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Switch the active `.env` between local, staging, and production profiles
 * while preserving each profile's values in gitignored snapshot files.
 */
#[AsCommand(name: 'env:switch')]
class EnvSwitchCommand extends Command
{
    protected $signature = 'env:switch
        {profile? : Profile to activate: local, staging, or production}
        {--list : List profiles and snapshot status}';

    protected $description = 'Switch active .env between profiles without losing per-profile config';

    public function __construct(
        private readonly EnvironmentProfileManager $profiles,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->displayList();
        }

        $profile = $this->argument('profile');

        if ($profile === null) {
            return $this->displayStatus();
        }

        $profile = strtolower(trim((string) $profile));

        try {
            $result = $this->profiles->activateProfile($profile);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result->skipped) {
            $this->warn("Already on [{$profile}] profile. No changes made.");

            return self::SUCCESS;
        }

        if ($result->previousProfile !== null) {
            $this->info("Saved {$result->previousProfile} → .env.{$result->previousProfile}");
        }

        if ($result->bootstrapped) {
            $this->info("Bootstrapped {$profile} from .env.{$profile}.example");
        }

        $this->info("Activated {$profile} → .env");
        $this->newLine();
        $this->line('Run: php artisan config:clear');

        return self::SUCCESS;
    }

    private function displayStatus(): int
    {
        $active = $this->profiles->activeProfile() ?? 'unknown';
        $envExists = File::exists($this->profiles->envPath());

        $this->line("Active profile: <info>{$active}</info>");
        $this->line('Active .env: '.($envExists ? 'present' : 'missing'));

        return self::SUCCESS;
    }

    private function displayList(): int
    {
        $rows = array_map(
            fn (array $status): array => [
                $status['profile'],
                $status['active'] ? 'yes' : 'no',
                $status['snapshot'] ? 'yes' : 'no',
            ],
            $this->profiles->profileStatuses(),
        );

        $this->table(['Profile', 'Active', 'Snapshot'], $rows);

        return self::SUCCESS;
    }
}
