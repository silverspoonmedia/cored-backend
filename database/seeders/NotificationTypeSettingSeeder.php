<?php

namespace Database\Seeders;

use App\Domain\Notification\Models\NotificationTypeSetting;
use App\Domain\Identity\Models\Role;
use App\Domain\Notification\Notifications\NotificationType;
use App\Domain\Notification\Repositories\Contracts\NotificationTypeSettingRepositoryInterface;
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

        NotificationTypeSetting::query()->firstOrCreate(
            ['type' => NotificationType::ServiceSubscriptionGrace->value],
            ['notify_subject_user' => true],
        );

        NotificationTypeSetting::query()->firstOrCreate(
            ['type' => NotificationType::ServiceSubscriptionExpired->value],
            ['notify_subject_user' => true],
        );

        app(NotificationTypeSettingRepositoryInterface::class)->flushCache();
    }
}
