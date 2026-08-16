<?php

namespace Tests\Domain\FileHosting\Feature\Http\Controllers\API\V1;

use App\Domain\FileHosting\Enums\WorkspaceInviteStatus;
use App\Domain\FileHosting\Enums\WorkspaceKind;
use App\Domain\FileHosting\Enums\WorkspaceMemberStatus;
use App\Domain\FileHosting\Enums\WorkspaceRole;
use App\Domain\FileHosting\Http\Resources\WorkspaceInviteResource;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\FileHosting\Models\WorkspaceInvite;
use App\Domain\FileHosting\Models\WorkspaceMember;
use App\Domain\Identity\Models\User;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\AssertsPayloadHygiene;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class FilePayloadHygieneTest extends TestCase
{
    use AssertsPayloadHygiene;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);
    }

    public function test_owner_file_show_includes_storage_fields(): void
    {
        $owner = $this->userWithRole('User');
        $file = $this->createFile($owner, null);

        $data = $this->actingAsJwt($owner)
            ->getJson('/api/v1/file-hosting/files/'.$file->id)
            ->assertOk()
            ->json('data');

        $this->assertSame($file->path, $data['path']);
        $this->assertSame($file->disk, $data['disk']);
        $this->assertArrayHasKey('region', $data);
    }

    public function test_workspace_collaborator_does_not_see_storage_fields(): void
    {
        $owner = $this->userWithRole('User');
        $editor = $this->userWithRole('User');
        $workspace = $this->createWorkspace($owner, WorkspaceKind::Team);
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::Owner,
            'status' => WorkspaceMemberStatus::Active,
            'joined_at' => now(),
        ]);
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $editor->id,
            'role' => WorkspaceRole::Editor,
            'status' => WorkspaceMemberStatus::Active,
            'joined_at' => now(),
        ]);
        $this->createFile($owner, $workspace->id);

        $files = $this->actingAsJwt($editor)
            ->getJson('/api/v1/file-hosting/browse?workspace_id='.$workspace->id)
            ->assertOk()
            ->json('data.files');

        $this->assertCount(1, $files);
        $this->assertJsonMissingPaths($files[0], ['path', 'disk', 'checksum', 'region']);
    }

    public function test_workspace_viewer_does_not_see_other_member_emails(): void
    {
        $owner = $this->userWithRole('User');
        $editor = $this->userWithRole('User');
        $workspace = $this->createWorkspace($owner, WorkspaceKind::Team);
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::Owner,
            'status' => WorkspaceMemberStatus::Active,
            'joined_at' => now(),
        ]);
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $editor->id,
            'role' => WorkspaceRole::Editor,
            'status' => WorkspaceMemberStatus::Active,
            'joined_at' => now(),
        ]);

        $asOwner = $this->actingAsJwt($owner)
            ->getJson('/api/v1/file-hosting/workspaces/'.$workspace->id.'/members')
            ->assertOk()
            ->json('data');

        $ownerRow = collect($asOwner)->firstWhere('user_id', $owner->id);
        $this->assertSame($owner->email, $ownerRow['user']['email'] ?? null);

        $asEditor = $this->actingAsJwt($editor)
            ->getJson('/api/v1/file-hosting/workspaces/'.$workspace->id.'/members')
            ->assertOk()
            ->json('data');

        $ownerSeenByEditor = collect($asEditor)->firstWhere('user_id', $owner->id);
        $selfSeenByEditor = collect($asEditor)->firstWhere('user_id', $editor->id);
        $this->assertArrayNotHasKey('email', $ownerSeenByEditor['user'] ?? []);
        $this->assertSame($editor->email, $selfSeenByEditor['user']['email'] ?? null);
    }

    public function test_invite_resource_omits_token_unless_explicitly_included(): void
    {
        $owner = $this->userWithRole('User');
        $workspace = $this->createWorkspace($owner, WorkspaceKind::Team);
        $invite = WorkspaceInvite::query()->create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => WorkspaceRole::Editor,
            'token' => 'secret-invite-token',
            'status' => WorkspaceInviteStatus::Pending,
            'invited_by' => $owner->id,
            'expires_at' => now()->addDay(),
        ]);

        $hidden = (new WorkspaceInviteResource($invite))->resolve(Request::create('/'));
        $this->assertArrayNotHasKey('token', $hidden);

        $shown = (new WorkspaceInviteResource($invite, includeToken: true))->resolve(Request::create('/'));
        $this->assertSame('secret-invite-token', $shown['token']);
    }

    private function createFile(User $user, ?string $workspaceId): HostedFile
    {
        return HostedFile::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspaceId,
            'folder_id' => null,
            'name' => 'sample-'.uniqid().'.jpg',
            'disk' => 'local',
            'path' => hash('sha256', uniqid('', true)),
            'size' => 1024,
            'mime' => 'image/jpeg',
        ]);
    }

    private function createWorkspace(User $user, WorkspaceKind $kind): Workspace
    {
        return Workspace::query()->create([
            'owner_user_id' => $user->id,
            'name' => $kind->value.'-workspace',
            'kind' => $kind,
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
