<?php

namespace App\Console\Commands;

use App\Support\Config\ConfigSecretVerifier;
use App\Support\Config\FreshConfigurationLoader;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'config:rebuild')]
class ConfigRebuildCommand extends Command
{
    protected $signature = 'config:rebuild';

    protected $description = 'Clear, verify secrets from .env, cache configuration, and verify the baked cache';

    public function __construct(
        private readonly ConfigSecretVerifier $verifier,
        private readonly FreshConfigurationLoader $freshLoader,
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->info('Clearing configuration cache...');
        $this->callSilent('config:clear');

        $this->components->info('Verifying secrets from .env...');
        $preCheck = $this->verifier->verifyFresh($this->laravel);

        if (! $preCheck->passed()) {
            $this->error(sprintf(
                'Secret verification failed before caching (%d error(s)). Fix .env and retry.',
                $preCheck->errorCount(),
            ));

            return self::FAILURE;
        }

        $this->components->info('Caching configuration...');
        $config = $this->freshLoader->load($this->laravel);
        $configPath = $this->laravel->getCachedConfigPath();

        $this->files->put(
            $configPath,
            '<?php return '.var_export($config, true).';'.PHP_EOL,
        );

        $this->components->info('Verifying baked configuration cache...');
        $postCheck = $this->verifier->verifyFresh($this->laravel);

        if (! $postCheck->passed()) {
            $this->callSilent('config:clear');

            $this->error(sprintf(
                'Baked configuration failed verification (%d error(s)). Cache was cleared again.',
                $postCheck->errorCount(),
            ));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'Configuration rebuilt successfully (cached=yes, %d check(s) passed).',
            count($postCheck->checks),
        ));

        return self::SUCCESS;
    }
}
