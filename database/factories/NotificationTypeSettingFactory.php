<?php

namespace Database\Factories;

use App\Models\NotificationTypeSetting;
use App\Notifications\Activity\NotificationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationTypeSetting>
 */
class NotificationTypeSettingFactory extends Factory
{
    protected $model = NotificationTypeSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => NotificationType::UserCreated->value,
            'notify_subject_user' => false,
        ];
    }
}
