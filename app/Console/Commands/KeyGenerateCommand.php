<?php

namespace App\Console\Commands;

use App\Support\EnvironmentKeyWriter;
use App\Support\EnvironmentMutationGuard;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Console\Prohibitable;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Overrides Laravel's key:generate to write APP_KEY from the .env file (not
 * config) using APP_PREVIOUS_KEYS as a stable anchor line. Supports rotation
 * via --force: the retired key is appended to APP_PREVIOUS_KEYS.
 */
#[AsCommand(name: 'key:generate')]
class KeyGenerateCommand extends Command
{
    use ConfirmableTrait, Prohibitable;

    protected $signature = 'key:generate
                    {--show : Display the key instead of modifying files}
                    {--force : Force the operation to run when in production}
                    {--force-past-backup : Proceed even if a pending backup exists}';

    protected $description = 'Set the application key';

    public function __construct(
        private readonly EnvironmentKeyWriter $keyWriter,
        private readonly EnvironmentMutationGuard $guard,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->isProhibited()) {
            return self::FAILURE;
        }

        $cipher = (string) config('app.cipher', 'AES-256-CBC');

        if ($this->option('show')) {
            $key = 'base64:'.base64_encode(Encrypter::generateKey($cipher));
            $this->line('<comment>'.$key.'</comment>');

            return self::SUCCESS;
        }

        $envPath = $this->laravel->environmentFilePath();
        $currentKey = $this->keyWriter->readAppKey($envPath);

        if ($currentKey !== null && $currentKey !== '' && (! $this->confirmToProceed())) {
            return self::FAILURE;
        }

        $rotateFrom = ($this->option('force') && $currentKey !== null && $currentKey !== '')
            ? $currentKey
            : null;

        try {
            $key = $this->guard->run(
                'key:generate',
                fn (): string => $this->keyWriter->writeAppKey($envPath, $rotateFrom, $cipher),
                file_exists($envPath) ? [$envPath => '.env'] : [],
                (bool) $this->option('force-past-backup'),
            );
        } catch (\RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->laravel['config']['app.key'] = $key;

        $this->components->info('Application key set successfully.');

        return self::SUCCESS;
    }
}
