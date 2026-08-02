<?php

namespace Database\Seeders;

use App\Support\Settings\Models\SystemSetting;
use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Support\Settings\SettingRegistry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds system_settings from config() defaults on first deploy only.
 * Re-running does not overwrite operator UI changes.
 */
class SettingsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (SettingRegistry::definitions() as $key => $definition) {
            $fromConfig = config($definition['config']);
            $value = $definition['type']->cast($fromConfig);

            SystemSetting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'group' => $definition['group'],
                    'type' => $definition['type']->value,
                    'value' => $value,
                ],
            );
        }

        app(SystemSettingRepositoryInterface::class)->flushCache();
    }
}
