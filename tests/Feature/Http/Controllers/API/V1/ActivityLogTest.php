<?php

namespace Tests\Feature\Http\Controllers\API\V1;

use App\Models\User;
use App\Services\User\UserManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_creating_a_user_writes_activity_log_and_role_assignment(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'Moderator',
        ])->assertCreated();

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'causer_type' => $admin->getMorphClass(),
            'causer_id' => $admin->id,
            'event' => 'created',
            'log_name' => 'user',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'event' => 'role_assigned',
            'log_name' => 'user',
        ]);
    }

    public function test_admin_can_list_activity_logs(): void
    {
        $admin = $this->userWithRole('Admin');
        $target = $this->userWithRole('User');

        app(UserManagementService::class)->update($admin, $target->id, [
            'name' => 'Renamed User',
        ]);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/activity-logs')
            ->assertOk()
            ->assertJsonPath('message', __('api/activity-log.list_success'))
            ->assertJsonStructure(['data' => [['id', 'log_name', 'event', 'created_at']]]);
    }

    public function test_moderator_without_permission_cannot_view_activity_logs(): void
    {
        $moderator = $this->userWithRole('Moderator');

        $this->actingAsJwt($moderator)
            ->getJson('/api/v1/activity-logs')
            ->assertForbidden();
    }

    public function test_user_activity_timeline_endpoint_returns_subject_entries(): void
    {
        $admin = $this->userWithRole('Admin');
        $target = $this->userWithRole('User');

        app(UserManagementService::class)->update($admin, $target->id, [
            'name' => 'Timeline User',
        ]);

        $this->actingAsJwt($admin)
            ->getJson("/api/v1/users/{$target->id}/activity-logs")
            ->assertOk()
            ->assertJsonPath('message', __('api/activity-log.subject_list_success'))
            ->assertJson(fn ($json) => $json->has('data')->etc());
    }

    public function test_show_returns_single_activity_entry(): void
    {
        $admin = $this->userWithRole('Admin');
        $target = $this->userWithRole('User');

        app(UserManagementService::class)->update($admin, $target->id, [
            'name' => 'Show Entry User',
        ]);

        $activity = Activity::query()->latest('id')->firstOrFail();

        $this->actingAsJwt($admin)
            ->getJson("/api/v1/activity-logs/{$activity->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $activity->id)
            ->assertJsonPath('message', __('api/activity-log.show_success'));
    }

    public function test_password_is_never_logged_on_user_update(): void
    {
        $admin = $this->userWithRole('Admin');
        $target = $this->userWithRole('User');

        $this->actingAsJwt($admin)->putJson("/api/v1/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'role' => 'User',
        ])->assertOk();

        $activities = Activity::query()
            ->forSubject($target->fresh())
            ->get();

        foreach ($activities as $activity) {
            $encoded = json_encode($activity->attribute_changes?->toArray() ?? []);
            $this->assertStringNotContainsString('newpassword123', (string) $encoded);
            $this->assertStringNotContainsString('password', (string) $encoded);
        }
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
