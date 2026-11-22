<?php

namespace App\Domain\Ai\Console\Commands;

use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Services\AiConnectionResolver;
use App\Domain\Ai\Services\AiConnectionService;
use App\Domain\Identity\Models\User;
use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * One-time transition: pull legacy Tier A AI env keys into Tier C
 * `ai_connections` rows, bind each consumer (Tier B settings), then those env
 * keys are deleted from every `.env*` file.
 *
 * Which env keys belong to which consumer is product knowledge: a product
 * publishes its rows in `ai.consumers` (`slug` / `endpoint_env` / `api_key_env` /
 * `connection_setting` / `model_setting`) from its own provider, so this command
 * names no product and imports nothing on a kernel-only install.
 *
 * Idempotent: an existing row with the same slug is refreshed in place.
 */
#[AsCommand(name: 'ai:import-legacy-env')]
class ImportLegacyEnvCommand extends Command
{
    protected $signature = 'ai:import-legacy-env
                            {--dry-run : Report what would be imported without writing}
                            {--skip-test : Do not probe the endpoint; import rows as pending}
                            {--actor= : User id credited as creator (defaults to the first Root user)}';

    protected $description = 'Import legacy per-product AI env keys into ai_connections + consumer bindings';

    public function handle(
        AiConnectionService $connections,
        AiConnectionResolver $resolver,
        SystemSettingRepositoryInterface $settings,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $skipTest = (bool) $this->option('skip-test');

        $sources = $this->sources();
        $importable = array_filter($sources, fn (array $source): bool => $source['endpoint'] !== '' && $source['api_key'] !== '');

        if ($importable === []) {
            $this->warn('No legacy AI env values found for any published AI consumer.');

            return self::SUCCESS;
        }

        $actor = $this->resolveActor();
        if ($actor === null && ! $dryRun) {
            $this->error('No user to credit as creator. Seed the Root role or pass --actor=<uuid>.');

            return self::FAILURE;
        }

        $this->table(
            ['source', 'slug', 'endpoint', 'key', 'model'],
            array_map(fn (array $source): array => [
                $source['label'],
                $source['slug'],
                $source['endpoint'],
                $source['api_key'] !== '' ? '…'.mb_substr($source['api_key'], -4) : '(empty)',
                $source['test_model'] !== '' ? $source['test_model'] : '(missing)',
            ], array_values($importable)),
        );

        if ($dryRun) {
            $this->info('[dry-run] No rows written.');

            return self::SUCCESS;
        }

        $bindings = [];
        $createdSlugs = [];

        foreach ($importable as $source) {
            if ($source['test_model'] === '') {
                $this->warn("Skipping {$source['label']}: no test model known (set the model first, then re-run).");

                continue;
            }

            try {
                $connection = $this->upsert($connections, $source, $actor);
            } catch (Throwable $e) {
                $this->error("Failed to import {$source['label']}: ".$e->getMessage());

                return self::FAILURE;
            }

            if (! $skipTest && ! $connection->isActive()) {
                $result = $connections->test($connection);
                if ($result->ok) {
                    $this->info("Test passed for {$connection->slug} ({$result->latencyMs} ms).");
                } else {
                    $this->warn("Test failed for {$connection->slug}: {$result->message} (row stays pending).");
                }
            }

            $createdSlugs[] = (string) $connection->slug;
            $bindings[$source['setting']] = (string) $connection->slug;
        }

        if ($bindings === []) {
            $this->error('Nothing imported.');

            return self::FAILURE;
        }

        $settings->upsertMany($bindings, (string) $actor->id);

        // First active connection becomes the default so unbound consumers keep working.
        foreach ($createdSlugs as $slug) {
            $model = AiConnection::query()->where('slug', $slug)->first();
            if ($model !== null && $model->isActive()) {
                $connections->setDefault($model);
                $this->info("Default AI connection: {$slug}");
                break;
            }
        }

        $resolver->forget();

        $this->newLine();
        $this->info('Imported '.count($bindings).' binding(s). Now remove the legacy keys from every .env* file:');
        foreach ($sources as $source) {
            $this->line('  - '.$source['endpoint_env']);
            $this->line('  - '.$source['api_key_env']);
        }
        $this->line('Then run: php artisan env:curate --sync-snapshot');

        return self::SUCCESS;
    }

    /**
     * @param  array{label: string, slug: string, endpoint: string, api_key: string, test_model: string, setting: string, endpoint_env: string, api_key_env: string}  $source
     */
    private function upsert(AiConnectionService $connections, array $source, User $actor): AiConnection
    {
        $existing = AiConnection::query()->where('slug', $source['slug'])->first();

        $payload = [
            'slug' => $source['slug'],
            'label' => $source['label'],
            'endpoint' => $source['endpoint'],
            'test_model' => $source['test_model'],
            'secret' => ['api_key' => $source['api_key']],
        ];

        if ($existing === null) {
            return $connections->create($actor, $payload);
        }

        return $connections->update($actor, $existing, $payload);
    }

    /**
     * @return list<array{label: string, slug: string, endpoint: string, api_key: string, test_model: string, setting: string, endpoint_env: string, api_key_env: string}>
     */
    private function sources(): array
    {
        $sources = [];

        foreach ((array) config('ai.consumers', []) as $consumer) {
            if (! is_array($consumer)) {
                continue;
            }

            $slug = trim((string) ($consumer['slug'] ?? ''));
            $endpointEnv = trim((string) ($consumer['endpoint_env'] ?? ''));
            $apiKeyEnv = trim((string) ($consumer['api_key_env'] ?? ''));
            $setting = trim((string) ($consumer['connection_setting'] ?? ''));
            $modelSetting = trim((string) ($consumer['model_setting'] ?? ''));

            if ($slug === '' || $endpointEnv === '' || $apiKeyEnv === '' || $setting === '') {
                continue;
            }

            $sources[] = [
                'label' => trim((string) ($consumer['label'] ?? '')) !== '' ? trim((string) $consumer['label']) : $slug,
                'slug' => $slug,
                'endpoint' => $this->envValue($endpointEnv),
                'api_key' => $this->envValue($apiKeyEnv),
                'test_model' => $modelSetting === '' ? '' : (string) setting_config($modelSetting, ''),
                'setting' => $setting,
                'endpoint_env' => $endpointEnv,
                'api_key_env' => $apiKeyEnv,
            ];
        }

        return $sources;
    }

    private function envValue(string $key): string
    {
        $value = Env::get($key);

        return is_string($value) ? trim($value) : '';
    }

    private function resolveActor(): ?User
    {
        $explicit = $this->option('actor');
        if (is_string($explicit) && $explicit !== '') {
            return User::query()->whereKey($explicit)->first();
        }

        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Root'))
            ->orderBy('created_at')
            ->first()
            ?? User::query()->orderBy('created_at')->first();
    }
}
