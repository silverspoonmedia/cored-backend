<?php

namespace Tests\Feature\Http\Controllers\API\V1;

use App\Models\ActivityNotification;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Activity\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_creating_a_user_fans_out_a_notification_to_root_and_admin(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'Moderator',
        ])->assertCreated();

        $this->assertDatabaseCount('activity_notifications', 1);

        $notification = ActivityNotification::query()->firstOrFail();
        $this->assertSame(NotificationType::UserCreated->value, $notification->type);
        $this->assertSame('jane@example.com', $notification->data['email']);

        $targetRoles = $notification->roles->pluck('name')->sort()->values()->all();
        $this->assertSame(['Admin', 'Root'], $targetRoles);
    }

    public function test_admin_sees_notification_but_moderator_does_not(): void
    {
        $admin = $this->userWithRole('Admin');
        $moderator = $this->userWithRole('Moderator');

        $this->emitUserCreated();

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Moderator lacks `view notification`: the surface itself is forbidden.
        $this->actingAsJwt($moderator)
            ->getJson('/api/v1/notifications')
            ->assertForbidden();
    }

    public function test_unread_count_and_mark_as_read(): void
    {
        $admin = $this->userWithRole('Admin');
        $notification = $this->emitUserCreated();

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread', 1);

        $this->actingAsJwt($admin)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk();

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread', 0);
    }

    public function test_mark_all_as_read(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->emitUserCreated();
        $this->emitUserCreated();

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked', 2);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertJsonPath('data.unread', 0);
    }

    public function test_cannot_mark_notification_out_of_role_scope(): void
    {
        // A notification targeted only at Root.
        $rootOnly = ActivityNotification::factory()->create();
        $rootOnly->roles()->sync(Role::query()->where('name', 'Root')->pluck('id'));

        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->postJson("/api/v1/notifications/{$rootOnly->id}/read")
            ->assertForbidden();
    }

    private function emitUserCreated(): ActivityNotification
    {
        $notification = ActivityNotification::factory()->create();
        $notification->roles()->sync(
            Role::query()->whereIn('name', ['Root', 'Admin'])->pluck('id'),
        );

        return $notification;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
