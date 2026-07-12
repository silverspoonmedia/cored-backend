<?php

namespace App\Console\Commands;

use App\Support\Encryption\DecryptedValueCache;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'encryption:flush-decrypted-cache')]
class EncryptionFlushDecryptedCacheCommand extends Command
{
    protected $signature = 'encryption:flush-decrypted-cache {context? : Optional decryption context key suffix}';

    protected $description = 'Flush decrypted-value cache entries used by envelope encryption';

    public function __construct(
        private readonly DecryptedValueCache $decryptedValueCache,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $context = $this->argument('context');

        if (is_string($context) && $context !== '') {
            $this->decryptedValueCache->forget($context);
            $this->info('Flushed decrypted cache context: '.$context);

            return self::SUCCESS;
        }

        $this->decryptedValueCache->flushAll();
        $this->warn('Full decrypted-cache flush is store-dependent. For Redis, prefer key scanning in production ops.');

        return self::SUCCESS;
    }
}
