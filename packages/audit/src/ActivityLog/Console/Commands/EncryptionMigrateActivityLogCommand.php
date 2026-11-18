<?php

namespace App\Domain\ActivityLog\Console\Commands;

use App\Domain\ActivityLog\Support\SensitiveActivityProperties;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'encryption:migrate-activity-log')]
class EncryptionMigrateActivityLogCommand extends Command
{
    protected $signature = 'encryption:migrate-activity-log
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Encrypt PII inside activity_log.properties (ip_address, user_agent, email) to OCI envelopes';

    public function __construct(
        private readonly EnvelopeEncryptionService $encryption,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $keys = SensitiveActivityProperties::keys();

        if (! SensitiveActivityProperties::enabled() || $keys === []) {
            $this->warn('activitylog.sensitive_properties is disabled or has no keys — nothing to migrate.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = 0;
        $fields = 0;

        // Raw query builder on purpose: never hydrate the Eloquent model, so
        // nothing re-decrypts or re-writes envelopes while chunking.
        DB::table('activity_log')
            ->select(['id', 'properties'])
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($keys, $dryRun, &$rows, &$fields): void {
                foreach ($chunk as $row) {
                    $properties = $this->decode($row->properties);

                    if ($properties === null) {
                        continue;
                    }

                    $encrypted = 0;

                    foreach ($keys as $key) {
                        $value = $properties[$key] ?? null;

                        if (! is_string($value) || $value === '' || EncryptedEnvelope::isEnvelopeJson($value)) {
                            continue;
                        }

                        $properties[$key] = $this->encryption->encrypt(
                            $value,
                            SensitiveActivityProperties::context(
                                $key,
                                (string) $row->id,
                                SensitiveActivityProperties::PURPOSE_MIGRATION,
                            ),
                        );

                        $encrypted++;
                    }

                    if ($encrypted === 0) {
                        continue;
                    }

                    $rows++;
                    $fields += $encrypted;

                    if ($dryRun) {
                        continue;
                    }

                    DB::table('activity_log')
                        ->where('id', $row->id)
                        ->update([
                            'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
                        ]);
                }
            }, 'id');

        $this->line(sprintf(
            '%s %d row(s) / %d field(s).',
            $dryRun ? 'Would migrate' : 'Migrated',
            $rows,
            $fields,
        ));

        if ($dryRun && $rows > 0) {
            $this->line('Dry run — no rows were written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->warn('Skipped a row with invalid properties JSON: '.$exception->getMessage());

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
