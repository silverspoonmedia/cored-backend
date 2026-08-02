<?php

namespace Database\Seeders;

use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Applies dev-only system_settings overrides when APP_ENV=local.
 *
 * Runs after SettingsSeeder; uses upsertMany so re-seeding refreshes local values.
 */
class LocalSettingsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('LocalSettingsSeeder skipped — APP_ENV is not local.');

            return;
        }

        $overrides = (array) config('settings-local.overrides', []);

        if ($overrides === []) {
            return;
        }

        app(SystemSettingRepositoryInterface::class)->upsertMany($overrides);
    }
}
