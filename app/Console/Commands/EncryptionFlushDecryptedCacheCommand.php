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

        $this->error('Bulk decrypted-cache flush is not implemented. Provide a context key suffix.');
        $this->line('Example: php artisan encryption:flush-decrypted-cache invoices.gateway_response_payload.{uuid}');

        return self::FAILURE;
    }
}
