<?php

namespace App\Support\Settings;

use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;

/**
 * Octane-safe runtime reader: DB override → config() fallback.
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
     * Resolve a config path via registry (first matching key) or direct config().
     */
    public function config(string $configPath, mixed $default = null): mixed
    {
        foreach (SettingRegistry::definitions() as $key => $definition) {
            if ($definition['config'] === $configPath) {
                return $this->get($key, $default);
            }
        }

        return config($configPath, $default);
    }
}
