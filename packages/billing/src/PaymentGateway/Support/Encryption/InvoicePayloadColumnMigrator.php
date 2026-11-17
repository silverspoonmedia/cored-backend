<?php

namespace App\Domain\PaymentGateway\Support\Encryption;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Support\Encryption\Contracts\LegacyColumnMigrator;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;

/**
 * `invoices.gateway_*_payload` P0 sweeper.
 *
 * The kernel command (`encryption:migrate-p0`) owns the schedule and the
 * column list, but the model is this package's — so the sweep registers here
 * and the command stays free of package imports.
 */
final class InvoicePayloadColumnMigrator implements LegacyColumnMigrator
{
    public const COLUMNS = ['gateway_request_payload', 'gateway_response_payload'];

    public function key(): string
    {
        return 'invoices.gateway_payload';
    }

    public function migrate(EnvelopeEncryptionService $encryption): int
    {
        $migrated = 0;

        foreach (self::COLUMNS as $column) {
            $migrated += $this->migrateColumn($encryption, $column);
        }

        return $migrated;
    }

    private function migrateColumn(EnvelopeEncryptionService $encryption, string $column): int
    {
        $migrated = 0;

        Invoice::query()
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use ($encryption, $column, &$migrated): void {
                foreach ($invoices as $invoice) {
                    $raw = $invoice->getRawOriginal($column);

                    if ($raw === null || $raw === '' || EncryptedEnvelope::isEnvelopeJson((string) $raw)) {
                        continue;
                    }

                    $plaintext = is_string($raw) ? $raw : json_encode($raw, JSON_THROW_ON_ERROR);

                    $invoice->forceFill([
                        $column => $encryption->encrypt(
                            $plaintext,
                            new DecryptionContext('invoices', (string) $invoice->id, $column, 'migration'),
                        ),
                    ])->saveQuietly();

                    $migrated++;
                }
            });

        return $migrated;
    }
}
