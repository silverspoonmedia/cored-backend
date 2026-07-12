<?php

namespace App\Console\Commands;

use App\Support\Config\ConfigSecretVerifier;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'config:verify-secrets')]
class ConfigVerifySecretsCommand extends Command
{
    protected $signature = 'config:verify-secrets
        {--fresh : Bootstrap a new application instance to read the active .env or cache file}';

    protected $description = 'Verify critical secrets are present and not template placeholders';

    public function __construct(
        private readonly ConfigSecretVerifier $verifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $result = $this->verifier->verifyFresh($this->laravel);
        } else {
            if ($this->laravel->configurationIsCached()) {
                $this->warn('Configuration is cached — values below come from bootstrap/cache/config.php, not a live .env reload.');
                $this->line('Use --fresh after config:clear to validate .env before caching.');
                $this->newLine();
            }

            $result = $this->verifier->verify();
        }

        $rows = array_map(
            fn ($check): array => [
                $check->label,
                $check->status,
                $check->severity,
            ],
            $result->checks,
        );

        $this->table(['Setting', 'Status', 'Severity'], $rows);

        if ($result->warningCount() > 0) {
            $this->warn(sprintf('%d warning(s).', $result->warningCount()));
        }

        if (! $result->passed()) {
            $this->error(sprintf('%d required secret(s) missing or still using template placeholders.', $result->errorCount()));

            return self::FAILURE;
        }

        $this->info(sprintf('All required secrets verified (%d check(s)).', count($result->checks)));

        return self::SUCCESS;
    }
}
