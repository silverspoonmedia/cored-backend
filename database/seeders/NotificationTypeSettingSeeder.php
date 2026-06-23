<?php

namespace Database\Seeders;

use App\Models\NotificationTypeSetting;
use App\Models\Role;
use App\Notifications\Activity\NotificationType;
use App\Repositories\Contracts\NotificationTypeSettingRepositoryInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds default per-type notification audiences (replaces hardcoded enum defaults).
 */
class NotificationTypeSettingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $userCreated = NotificationTypeSetting::query()->firstOrCreate(
            ['type' => NotificationType::UserCreated->value],
            ['notify_subject_user' => false],
        );

        $userCreated->roles()->sync(
            Role::query()
                ->where('guard_name', 'api')
                ->whereIn('name', ['Root', 'Admin'])
                ->pluck('id')
                ->all(),
        );

        NotificationTypeSetting::query()->firstOrCreate(
            ['type' => NotificationType::AccountUpdated->value],
            ['notify_subject_user' => true],
        );

        app(NotificationTypeSettingRepositoryInterface::class)->flushCache();
    }
}
