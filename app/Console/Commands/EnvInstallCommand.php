<?php

namespace App\Console\Commands;

use App\Support\EnvironmentKeyWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Scaffold `.env` from a committed profile template (local, staging, production).
 *
 * Laravel loads a single `.env` at boot — this command copies the right
 * `.env.{profile}.example` into `.env`, then generates APP_KEY and JWT_SECRET
 * when they are empty. See docs/environment.md.
 */
#[AsCommand(name: 'env:install')]
class EnvInstallCommand extends Command
{
    /** @var array<string, string> */
    private const PROFILES = [
        'local' => '.env.local.example',
        'staging' => '.env.staging.example',
        'production' => '.env.production.example',
    ];

    protected $signature = 'env:install
        {profile : Profile to install: local, staging, or production}
        {--force : Overwrite an existing .env file}';

    protected $description = 'Create .env from a profile template (local, staging, or production) and generate secrets';

    public function __construct(
        private readonly EnvironmentKeyWriter $keyWriter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $profile = strtolower(trim((string) $this->argument('profile')));

        if (! array_key_exists($profile, self::PROFILES)) {
            $this->error("Invalid profile [{$profile}]. Choose: ".implode(', ', array_keys(self::PROFILES)).'.');

            return self::FAILURE;
        }

        $templatePath = base_path(self::PROFILES[$profile]);
        $envPath = base_path('.env');

        if (! File::exists($templatePath)) {
            $this->error('Profile template not found: '.self::PROFILES[$profile]);

            return self::FAILURE;
        }

        if (File::exists($envPath) && ! $this->option('force')) {
            $this->error('.env already exists. Use --force to overwrite.');

            return self::FAILURE;
        }

        File::copy($templatePath, $envPath);
        $this->info('Copied '.self::PROFILES[$profile].' → .env');

        try {
            $this->keyWriter->writeAppKey($envPath);
            $this->info('Generated APP_KEY.');
        } catch (\RuntimeException $exception) {
            $this->warn($exception->getMessage());
        }

        if ($this->jwtSecretIsEmpty($envPath)) {
            $this->callSilent('jwt:secret', ['--force' => true]);
            $this->info('Generated JWT_SECRET.');
        }

        $this->newLine();
        $this->line('Next steps:');
        $this->line('  • Set APP_URL, DB_* credentials, and any integration secrets.');
        $this->line('  • For staging/production: configure MAIL_*, PUSHER_*, CUSTOM_S3_*, HCAPTCHA_*.');
        $this->line('  • Run: php artisan migrate');
        $this->line('  • See docs/environment.md for the full checklist.');

        return self::SUCCESS;
    }

    private function jwtSecretIsEmpty(string $envPath): bool
    {
        $contents = File::get($envPath);

        if (! preg_match('/^JWT_SECRET=(.*)$/m', $contents, $matches)) {
            return true;
        }

        $value = trim($matches[1], " \t\n\r\0\x0B\"'");

        return $value === '';
    }
}
