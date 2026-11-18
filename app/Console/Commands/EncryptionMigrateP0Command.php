<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use App\Support\Encryption\LegacyColumnMigratorRegistry;
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
        private readonly LegacyColumnMigratorRegistry $packageMigrators,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $counts = [
            'users.two_factor_secret' => $this->migrateUsers(),
        ];

        // Package-owned P0 columns (e.g. `invoices.*` from `silverspoonmedia/billing`)
        // register themselves; the kernel command never imports a package model.
        foreach ($this->packageMigrators->all() as $migrator) {
            $counts[$migrator->key()] = $migrator->migrate($this->encryption);
        }

        $counts['cache.value'] = $this->migrateSensitiveCacheRows();

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
