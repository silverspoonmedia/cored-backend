<?php

namespace App\Support\Settings;

use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;

/**
 * Octane-safe runtime reader: cached DB snapshot → config() literal fallback.
 */
final class SettingsReader
{
    public function __construct(
        private readonly SystemSettingRepositoryInterface $repository,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $snapshot = $this->repository->snapshot();

        if (array_key_exists($key, $snapshot)) {
            return $snapshot[$key];
        }

        $definition = SettingRegistry::get($key);

        if ($definition !== null) {
            $fromConfig = config($definition['config']);

            if ($fromConfig !== null) {
                return $definition['type']->cast($fromConfig);
            }
        }

        return $default;
    }

    /**
     * Resolve a config path via registry index or direct config().
     */
    public function config(string $configPath, mixed $default = null): mixed
    {
        $registryKey = SettingRegistry::configPathIndex()[$configPath] ?? null;

        if ($registryKey !== null) {
            return $this->get($registryKey, $default);
        }

        return config($configPath, $default);
    }
}
