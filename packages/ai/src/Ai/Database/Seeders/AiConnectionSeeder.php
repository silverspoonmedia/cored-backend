<?php

namespace App\Domain\Ai\Database\Seeders;

use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Services\AiConnectionResolver;
use App\Domain\Ai\Services\AiConnectionService;
use App\Domain\Identity\Models\User;
use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Support\Settings\SettingRegistry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Local-only wiring for the core Ai domain.
 *
 * Reads a gitignored data file (endpoint + API key per slug), upserts
 * `ai_connections` rows through {@see AiConnectionService}, probes each new row,
 * binds consumer slug settings (Microstock vision/thinking) and
 * promotes the flagged entry to default.
 *
 * Same contract as {@see \Database\Seeders\LocalSettingsSeeder}: `APP_ENV=local` only, idempotent
 * upsert so re-seeding refreshes local values. Staging/production wire
 * connections by hand at Settings → AI connections.
 *
 * Idempotency note: re-seeding with an unchanged endpoint + key does NOT demote
 * a row — `AiConnectionService::update()` only resets the Test gate when the
 * identity (endpoint / driver / secret) actually changed.
 *
 * The data file is plain PHP, loaded in an isolated scope, so it can hoist a
 * shared value once and reuse it — either through a top-level `defaults` block
 * (preferred when every consumer uses the same endpoint + key) or a plain
 * variable:
 *
 *     return [
 *         'defaults' => ['endpoint' => 'https://…', 'api_key' => 'sk-…'],
 *         'connections' => [
 *             'a' => ['binds' => ['microstock.ai.vision.connection']],   // inherits both
 *             'b' => ['binds' => […], 'test_model' => 'other-model'],    // overrides one
 *         ],
 *     ];
 *
 * An entry value always wins over `defaults`; an explicitly blank value in the
 * entry (e.g. `'api_key' => ''`) therefore parks that one entry as skipped.
 *
 * @see database/seeders/data/ai-connections.local.example.php
 * @see docs/domains/ai/README.md
 */
class AiConnectionSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Transport overrides copied from the data file when present. */
    private const OPTIONAL_KEYS = ['timeout_seconds', 'connect_timeout_seconds', 'max_retries', 'extra_headers'];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('AiConnectionSeeder skipped — APP_ENV is not local.');

            return;
        }

        $data = $this->loadData();

        if ($data === null) {
            return;
        }

        $actor = $this->resolveActor();

        if ($actor === null) {
            $this->command?->warn('AiConnectionSeeder skipped — no user to credit as creator.');
            $this->command?->line('Seed the users first (php artisan db:seed), then re-run this seeder.');

            return;
        }

        $connections = app(AiConnectionService::class);
        $settings = app(SystemSettingRepositoryInterface::class);
        $resolver = app(AiConnectionResolver::class);

        $bindings = [];
        $seeded = [];
        $rows = [];
        $defaultSlug = null;

        foreach ($data['connections'] as $rawSlug => $entry) {
            $slug = $this->normalizeSlugKey($rawSlug);

            if ($slug === null) {
                $this->command?->warn("Skipped an entry: '{$rawSlug}' is not a usable slug key (allowed: a-z 0-9 . _ -).");

                continue;
            }

            if (! is_array($entry)) {
                $this->command?->warn("Skipped {$slug}: the entry must be an array.");

                continue;
            }

            // `defaults` carries the transport values every entry shares, so a
            // file where all consumers use one endpoint + key does not repeat
            // them. An entry value always wins.
            $entry += $data['defaults'];

            $endpoint = trim((string) ($entry['endpoint'] ?? ''));
            $apiKey = trim((string) ($entry['api_key'] ?? ''));

            if ($endpoint === '' || $apiKey === '') {
                $this->command?->warn("Skipped {$slug}: fill in both 'endpoint' and 'api_key' in the data file.");

                continue;
            }

            $binds = $this->normalizeBinds($entry['binds'] ?? [], $slug);

            $testModel = trim((string) ($entry['test_model'] ?? ''));
            if ($testModel === '') {
                $testModel = $this->modelFor($binds);
            }

            if ($testModel === '') {
                $this->command?->warn("Skipped {$slug}: no test model known. Set 'test_model' here, or the consumer's model setting (Settings → Microstock).");

                continue;
            }

            $label = trim((string) ($entry['label'] ?? ''));

            $payload = [
                'slug' => $slug,
                'label' => $label !== '' ? $label : $slug,
                'endpoint' => $endpoint,
                'test_model' => $testModel,
                'secret' => ['api_key' => $apiKey],
            ];

            foreach (self::OPTIONAL_KEYS as $optional) {
                if (array_key_exists($optional, $entry)) {
                    $payload[$optional] = $entry[$optional];
                }
            }

            try {
                $existing = AiConnection::query()->where('slug', $slug)->first();

                $connection = $existing === null
                    ? $connections->create($actor, $payload)
                    : $connections->update($actor, $existing, $payload);
            } catch (Throwable $e) {
                $this->command?->error("Failed to seed {$slug}: ".$e->getMessage());

                continue;
            }

            if ($data['probe'] && ! $connection->isActive()) {
                try {
                    $result = $connections->test($connection);
                    $connection = $result->connection ?? $connection;

                    if ($result->ok) {
                        $this->command?->info("Probe passed for {$slug} ({$result->latencyMs} ms).");
                    } else {
                        $this->command?->warn("Probe failed for {$slug}: {$result->message} (row stays pending — Test it again from Settings → AI connections).");
                    }
                } catch (Throwable $e) {
                    $this->command?->warn("Probe errored for {$slug}: ".$e->getMessage().' (row stays pending).');
                }
            }

            foreach ($binds as $setting) {
                $bindings[$setting] = $slug;
            }

            if ($defaultSlug === null && ($entry['is_default'] ?? false) === true) {
                $defaultSlug = $slug;
            }

            $seeded[] = $slug;
            $rows[] = [
                $slug,
                (string) $connection->status?->value,
                (string) ($connection->secret_hint ?? ''),
                $binds === [] ? '—' : implode(', ', $binds),
            ];
        }

        if ($seeded === []) {
            $this->command?->warn('AiConnectionSeeder: nothing was seeded. Fill in the data file and re-run.');

            return;
        }

        $this->promoteDefault($connections, $defaultSlug);

        if ($bindings !== []) {
            $settings->upsertMany($bindings, (string) $actor->id);
        }

        foreach ($seeded as $slug) {
            $resolver->forget($slug);
        }

        $this->command?->table(['slug', 'status', 'key', 'bound settings'], $rows);

        if ($bindings === []) {
            $this->command?->warn('No consumer binding written — consumers still resolve the default connection.');
        } else {
            $this->command?->info('Bound '.count($bindings).' consumer setting(s).');
        }
    }

    /**
     * @return array{probe: bool, defaults: array<string, mixed>, connections: array<mixed, mixed>}|null
     */
    private function loadData(): ?array
    {
        $path = trim((string) config('ai.seed.path', ''));

        if ($path === '') {
            $this->command?->warn('AiConnectionSeeder skipped — config(ai.seed.path) is empty.');

            return null;
        }

        if (! $this->isAbsolutePath($path)) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            $this->command?->warn("AiConnectionSeeder skipped — no data file at {$path}.");
            $this->command?->line('Copy database/seeders/data/ai-connections.local.example.php to ai-connections.local.php and fill in the API keys.');

            return null;
        }

        // Isolated scope: the data file is allowed to declare local variables
        // (e.g. a shared `$key` / `$endpoint`) and must not be able to clobber
        // the seeder's own `$path` / `$data`.
        $data = (static function (string $__aiSeedPath): mixed {
            return require $__aiSeedPath;
        })($path);

        if (! is_array($data) || ! is_array($data['connections'] ?? null) || $data['connections'] === []) {
            $this->command?->warn("AiConnectionSeeder skipped — {$path} has no 'connections' map.");

            return null;
        }

        return [
            'probe' => (bool) ($data['probe'] ?? true),
            'defaults' => $this->normalizeDefaults($data['defaults'] ?? []),
            'connections' => $data['connections'],
        ];
    }

    /**
     * Transport values an entry may inherit. `binds` / `is_default` / `label`
     * are deliberately excluded — those are per-entry by nature, and a shared
     * label would make every row read the same.
     *
     * @return array<string, mixed>
     */
    private function normalizeDefaults(mixed $defaults): array
    {
        if (! is_array($defaults)) {
            return [];
        }

        $allowed = array_merge(['endpoint', 'api_key', 'test_model'], self::OPTIONAL_KEYS);

        $normalized = [];

        foreach ($defaults as $key => $value) {
            if (is_string($key) && in_array($key, $allowed, true)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('#^(?:[A-Za-z]:[\\\\/]|/|\\\\)#', $path) === 1;
    }

    private function normalizeSlugKey(mixed $key): ?string
    {
        if (! is_string($key) && ! is_int($key)) {
            return null;
        }

        $slug = strtolower(trim((string) $key));

        if ($slug === '' || preg_match('/[^a-z0-9._-]/', $slug) === 1) {
            return null;
        }

        return $slug;
    }

    /**
     * Registry-known binding settings only — an unknown key would be silently
     * dropped by `upsertMany`, which hides typos.
     *
     * @return list<string>
     */
    private function normalizeBinds(mixed $binds, string $slug): array
    {
        if (! is_array($binds)) {
            return [];
        }

        $normalized = [];

        foreach ($binds as $setting) {
            if (! is_string($setting) || trim($setting) === '') {
                continue;
            }

            $setting = trim($setting);

            if (SettingRegistry::get($setting) === null) {
                $this->command?->warn("Ignored unknown binding '{$setting}' on {$slug} — not a registered setting.");

                continue;
            }

            $normalized[] = $setting;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Model for the Test probe, inherited from the bound consumer's Tier B setting.
     *
     * @param  list<string>  $binds
     */
    private function modelFor(array $binds): string
    {
        $map = (array) config('ai.seed.binding_models', []);

        foreach ($binds as $setting) {
            $configPath = $map[$setting] ?? null;

            if (! is_string($configPath) || $configPath === '') {
                continue;
            }

            $value = setting_config($configPath, '');

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function promoteDefault(AiConnectionService $connections, ?string $slug): void
    {
        if ($slug === null) {
            return;
        }

        $connection = AiConnection::query()->where('slug', $slug)->first();

        if ($connection === null || ! $connection->isActive()) {
            $this->command?->warn("Could not make {$slug} the default — only an active (probed) connection can be the default.");

            return;
        }

        if ($connection->is_default) {
            return;
        }

        try {
            $connections->setDefault($connection);
            $this->command?->info("Default AI connection: {$slug}");
        } catch (Throwable $e) {
            $this->command?->warn("Could not make {$slug} the default: ".$e->getMessage());
        }
    }

    private function resolveActor(): ?User
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Root'))
            ->orderBy('created_at')
            ->first()
            ?? User::query()->orderBy('created_at')->first();
    }
}
