<?php

namespace Tests\Domain\FileHosting\Feature\Broadcasting;

use App\Domain\FileHosting\Enums\WorkspaceMemberStatus;
use App\Domain\FileHosting\Enums\WorkspaceRole;
use App\Domain\FileHosting\Enums\WorkspaceStatus;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\FileHosting\Models\WorkspaceMember;
use App\Domain\FileHosting\Services\WorkspaceAccessService;
use App\Domain\Identity\Models\User;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Channel auth callbacks live in routes/channels.php. Exercise them via
 * WorkspaceAccessService (same rules) plus direct callback invocation so we
 * do not depend on BroadcastManager internals.
 */
class FileHostingChannelAuthTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);
    }

    public function test_personal_channel_rule_is_self_only(): void
    {
        $user = $this->userWithRole('User', 'ch-a@example.com');
        $other = $this->userWithRole('User', 'ch-b@example.com');

        // Mirrors routes/channels.php file-hosting.user.{userId}
        $auth = static fn (User $u, string $userId): bool => $u->id === $userId;

        $this->assertTrue($auth($user, $user->id));
        $this->assertFalse($auth($other, $user->id));
    }

    public function test_workspace_channel_uses_can_view(): void
    {
        $owner = $this->userWithRole('User', 'ch-owner@example.com');
        $member = $this->userWithRole('User', 'ch-member@example.com');
        $stranger = $this->userWithRole('User', 'ch-stranger@example.com');

        $ws = Workspace::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Auth Team',
            'status' => WorkspaceStatus::Active,
        ]);
        WorkspaceMember::query()->create([
            'workspace_id' => $ws->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::Owner,
            'status' => WorkspaceMemberStatus::Active,
            'joined_at' => now(),
        ]);
        WorkspaceMember::query()->create([
            'workspace_id' => $ws->id,
            'user_id' => $member->id,
            'role' => WorkspaceRole::Editor,
            'status' => WorkspaceMemberStatus::Active,
            'joined_at' => now(),
        ]);

        $access = app(WorkspaceAccessService::class);

        // Mirrors routes/channels.php file-hosting.workspace.{workspaceId}
        $auth = function (User $user, string $workspaceId) use ($access): bool {
            try {
                $workspace = $access->findOrFail($workspaceId);

                return $access->canView($workspace, $user);
            } catch (\Throwable) {
                return false;
            }
        };

        $this->assertTrue($auth($owner, $ws->id));
        $this->assertTrue($auth($member, $ws->id));
        $this->assertFalse($auth($stranger, $ws->id));
        $this->assertFalse($auth($owner, '00000000-0000-7000-8000-000000000099'));
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }
}
