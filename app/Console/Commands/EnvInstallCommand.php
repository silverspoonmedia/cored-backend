<?php

namespace App\Console\Commands;

use App\Support\EnvironmentProfileManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Scaffold `.env` from a committed profile template (local, staging, production).
 *
 * Laravel loads a single `.env` at boot — this command copies the right
 * `.env.{profile}.example` into `.env`, seeds a profile snapshot, and
 * generates secrets when they are empty. See docs/platform/environment.md.
 */
#[AsCommand(name: 'env:install')]
class EnvInstallCommand extends Command
{
    protected $signature = 'env:install
        {profile : Profile to install: local, staging, or production}
        {--force : Overwrite an existing .env file}
        {--force-past-backup : Proceed even if a pending backup exists}';

    protected $description = 'Create .env from a profile template (local, staging, or production) and generate secrets';

    public function __construct(
        private readonly EnvironmentProfileManager $profiles,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $profile = strtolower(trim((string) $this->argument('profile')));

        if (! $this->profiles->isValidProfile($profile)) {
            $this->error('Invalid profile ['.$profile.']. Choose: '.implode(', ', $this->profiles->profiles()).'.');

            return self::FAILURE;
        }

        if (! File::exists($this->profiles->templatePath($profile))) {
            $this->error('Profile template not found: .env.'.$profile.'.example');

            return self::FAILURE;
        }

        if (File::exists($this->profiles->envPath()) && ! $this->option('force')) {
            $this->error('.env already exists. Use --force to overwrite.');

            return self::FAILURE;
        }

        if ($this->option('force') && File::exists($this->profiles->envPath()) && ! $this->option('no-interaction')) {
            if (! $this->confirm('This will overwrite .env and the profile snapshot (a backup is created first). Continue?')) {
                $this->warn('Install cancelled.');

                return self::FAILURE;
            }
        }

        try {
            $warnings = $this->profiles->installFromTemplate(
                $profile,
                (bool) $this->option('force-past-backup'),
            );
        } catch (InvalidArgumentException|\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        $this->info('Copied .env.'.$profile.'.example → .env');
        $this->info('Seeded snapshot → .env.'.$profile);
        $this->info('Active profile → '.$profile);

        $this->callSilent('config:clear');
        $this->line('Configuration cache cleared.');

        $this->newLine();
        $this->line('Next steps:');
        $this->line('  • Set APP_URL, DB_* credentials, and any integration secrets.');
        $this->line('  • For staging/production: configure MAIL_*, PUSHER_*, ARCHIVD_S3_CE_*, HCAPTCHA_*.');
        $this->line('  • Run: php artisan migrate');
        $this->line('  • See docs/platform/environment.md for the full checklist.');

        return self::SUCCESS;
    }
}
