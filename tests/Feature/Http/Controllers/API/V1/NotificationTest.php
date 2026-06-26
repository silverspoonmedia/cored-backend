<?php

namespace Tests\Feature\Http\Controllers\API\V1;

use App\Events\UserActivityNotificationReceived;
use App\Models\ActivityNotification;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Activity\NotificationType;
use App\Services\Notification\NotificationService;
use App\Services\User\UserManagementService;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

    public function test_moderator_can_access_surface_but_does_not_see_root_admin_broadcast(): void
    {
        $admin = $this->userWithRole('Admin');
        $moderator = $this->userWithRole('Moderator');

        $this->emitUserCreated();

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Moderator now holds `view notification` (per-user notifications can reach
        // any role), but is not in the Root/Admin broadcast audience → empty list.
        $this->actingAsJwt($moderator)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_personal_notification_reaches_only_the_targeted_user(): void
    {
        $alice = $this->userWithRole('User');
        $bob = $this->userWithRole('User');

        app(NotificationService::class)->sendToUsers(
            NotificationType::AccountUpdated,
            [$alice->id],
            ['name' => $alice->name, 'actor' => 'Admin User'],
        );

        $this->actingAsJwt($alice)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', NotificationType::AccountUpdated->value);

        // Bob shares the same role but was not targeted → sees nothing
        // (per-user is not a role broadcast).
        $this->actingAsJwt($bob)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_updating_a_user_sends_a_personal_notification_to_that_user(): void
    {
        $admin = $this->userWithRole('Admin');
        $target = $this->userWithRole('Moderator');

        $this->actingAsJwt($admin)
            ->putJson("/api/v1/users/{$target->id}", ['name' => 'Renamed'])
            ->assertOk();

        $personal = ActivityNotification::query()
            ->where('type', NotificationType::AccountUpdated->value)
            ->firstOrFail();
        $this->assertTrue($personal->targetUsers->contains('id', $target->id));
        $this->assertCount(0, $personal->roles);

        // The edited user sees their personal notification.
        $this->actingAsJwt($target)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_self_edit_does_not_create_a_personal_notification(): void
    {
        // Self-edit can't reach the HTTP endpoint (UserPolicy denies equal-level
        // updates), so assert the service guard directly: editing yourself emits
        // no AccountUpdated notification.
        $admin = $this->userWithRole('Admin');

        app(UserManagementService::class)
            ->update($admin, $admin->id, ['name' => 'Myself Renamed']);

        $this->assertDatabaseMissing('activity_notifications', [
            'type' => NotificationType::AccountUpdated->value,
        ]);
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

    public function test_cannot_mark_notification_out_of_scope(): void
    {
        // A broadcast targeted only at Root, with no per-user target.
        $rootOnly = ActivityNotification::factory()->create();
        $rootOnly->roles()->sync(Role::query()->where('name', 'Root')->pluck('id'));

        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->postJson("/api/v1/notifications/{$rootOnly->id}/read")
            ->assertForbidden();
    }

    public function test_emit_broadcasts_realtime_ping_when_delivery_is_realtime_and_pusher_is_configured(): void
    {
        Event::fake([UserActivityNotificationReceived::class]);

        $this->configurePusherBroadcasting();
        config(['notifications.delivery' => 'realtime']);

        $alice = $this->userWithRole('User');

        app(NotificationService::class)->sendToUsers(
            NotificationType::AccountUpdated,
            [$alice->id],
            ['name' => $alice->name, 'actor' => 'Admin User'],
        );

        Event::assertDispatched(UserActivityNotificationReceived::class, function (UserActivityNotificationReceived $event) use ($alice): bool {
            return $event->userId === $alice->id;
        });
    }

    public function test_emit_does_not_broadcast_when_delivery_is_polling(): void
    {
        Event::fake([UserActivityNotificationReceived::class]);

        $this->configurePusherBroadcasting();
        config(['notifications.delivery' => 'polling']);

        $alice = $this->userWithRole('User');

        app(NotificationService::class)->sendToUsers(
            NotificationType::AccountUpdated,
            [$alice->id],
            ['name' => $alice->name, 'actor' => 'Admin User'],
        );

        Event::assertNotDispatched(UserActivityNotificationReceived::class);
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

    private function configurePusherBroadcasting(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => [],
            ],
        ]);

        $this->app->forgetInstance(BroadcastManager::class);
    }
}
