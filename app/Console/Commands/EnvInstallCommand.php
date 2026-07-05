<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
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

        $this->generateAppKey($envPath);

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

    private function generateAppKey(string $envPath): void
    {
        $key = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));

        $contents = File::get($envPath);
        $updated = preg_replace(
            '/^APP_KEY=.*$/m',
            'APP_KEY="'.$key.'"',
            $contents,
            count: $count,
        );

        if ($count === 0) {
            $this->warn('APP_KEY line not found in .env — run php artisan key:generate manually.');

            return;
        }

        File::put($envPath, $updated);
        $this->info('Generated APP_KEY.');
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
