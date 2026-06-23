<?php

namespace Tests\Unit\Repositories\Eloquent;

use App\Models\NotificationTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Activity\NotificationType;
use App\Repositories\Eloquent\NotificationTypeSettingRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTypeSettingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_resolve_audience_merges_roles_users_and_subject_user(): void
    {
        $repo = new NotificationTypeSettingRepository;

        $setting = NotificationTypeSetting::factory()->create([
            'type' => NotificationType::AccountUpdated->value,
            'notify_subject_user' => true,
        ]);

        $extraUser = User::factory()->create();
        $subjectUser = User::factory()->create();

        $setting->roles()->sync(
            Role::query()->whereIn('name', ['Admin'])->pluck('id'),
        );
        $setting->targetUsers()->sync([$extraUser->id]);

        $repo->flushCache();

        $audience = $repo->resolveAudience(
            NotificationType::AccountUpdated,
            ['subject_user_id' => $subjectUser->id],
        );

        $this->assertSame(['Admin'], $audience['roles']);
        $this->assertEqualsCanonicalizing(
            [$extraUser->id, $subjectUser->id],
            $audience['users'],
        );
    }

    public function test_resolve_audience_honours_explicit_role_override(): void
    {
        $repo = new NotificationTypeSettingRepository;

        NotificationTypeSetting::factory()->create([
            'type' => NotificationType::UserCreated->value,
        ])->roles()->sync(
            Role::query()->whereIn('name', ['Root'])->pluck('id'),
        );

        $repo->flushCache();

        $audience = $repo->resolveAudience(
            NotificationType::UserCreated,
            [],
            ['Moderator'],
        );

        $this->assertSame(['Moderator'], $audience['roles']);
    }
}
