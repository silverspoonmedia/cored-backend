<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'encryption:migrate-p0')]
class EncryptionMigrateP0Command extends Command
{
    protected $signature = 'encryption:migrate-p0';

    protected $description = 'Migrate P0 sensitive columns from plaintext or legacy Crypt values to OCI envelopes';

    public function __construct(
        private readonly EnvelopeEncryptionService $encryption,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $counts = [
            'users.two_factor_secret' => $this->migrateUsers(),
            'invoices.gateway_request_payload' => $this->migrateInvoiceColumn('gateway_request_payload'),
            'invoices.gateway_response_payload' => $this->migrateInvoiceColumn('gateway_response_payload'),
            'cache.value' => $this->migrateSensitiveCacheRows(),
        ];

        foreach ($counts as $target => $count) {
            $this->line(sprintf('%s: %d row(s) migrated', $target, $count));
        }

        return self::SUCCESS;
    }

    private function migrateUsers(): int
    {
        $migrated = 0;

        User::query()
            ->whereNotNull('two_factor_secret')
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$migrated): void {
                foreach ($users as $user) {
                    $raw = $user->getRawOriginal('two_factor_secret');

                    if (! is_string($raw) || $raw === '' || EncryptedEnvelope::isEnvelopeJson($raw)) {
                        continue;
                    }

                    $user->forceFill([
                        'two_factor_secret' => $this->encryption->migrateLegacyValue($raw),
                    ])->saveQuietly();

                    $migrated++;
                }
            });

        return $migrated;
    }

    private function migrateInvoiceColumn(string $column): int
    {
        $migrated = 0;

        Invoice::query()
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use ($column, &$migrated): void {
                foreach ($invoices as $invoice) {
                    $raw = $invoice->getRawOriginal($column);

                    if ($raw === null || $raw === '' || EncryptedEnvelope::isEnvelopeJson((string) $raw)) {
                        continue;
                    }

                    $plaintext = is_string($raw) ? $raw : json_encode($raw, JSON_THROW_ON_ERROR);

                    $invoice->forceFill([
                        $column => $this->encryption->encrypt(
                            $plaintext,
                            new DecryptionContext('invoices', (string) $invoice->id, $column, 'migration'),
                        ),
                    ])->saveQuietly();

                    $migrated++;
                }
            });

        return $migrated;
    }

    private function migrateSensitiveCacheRows(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('cache')) {
            return 0;
        }

        $migrated = 0;
        $prefixes = (array) config('encryption.sensitive_cache_prefixes', []);

        // `cache` uses `key` as primary key — no `id` column (see create_cache_table migration).
        $rows = DB::table('cache')->orderBy('key')->lazy();

        foreach ($rows as $row) {
            $key = (string) $row->key;
            $matches = false;

            foreach ($prefixes as $prefix) {
                if (str_contains($key, (string) $prefix)) {
                    $matches = true;
                    break;
                }
            }

            if (! $matches) {
                continue;
            }

            $value = (string) $row->value;

            if ($value === '' || EncryptedEnvelope::isEnvelopeJson($value)) {
                continue;
            }

            DB::table('cache')->where('key', $row->key)->update([
                'value' => $this->encryption->encrypt(
                    $value,
                    new DecryptionContext('cache', $key, 'value', 'migration'),
                ),
            ]);

            $migrated++;
        }

        return $migrated;
    }
}
