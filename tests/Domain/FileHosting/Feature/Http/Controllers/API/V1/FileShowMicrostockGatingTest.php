<?php

namespace Tests\Domain\FileHosting\Feature\Http\Controllers\API\V1;

use App\Domain\FileHosting\Enums\WorkspaceKind;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class FileShowMicrostockGatingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);
    }

    public function test_personal_file_payload_has_no_microstock_key(): void
    {
        $user = $this->userWithRole('User');
        $file = $this->createFile($user, workspaceId: null);

        $data = $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$file->id)
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('workspace', $data);
        $this->assertNull($data['workspace']);
        $this->assertArrayNotHasKey('microstock', $data);
    }

    public function test_team_workspace_file_payload_has_no_microstock_key(): void
    {
        $user = $this->userWithRole('User');
        $workspace = $this->createWorkspace($user, WorkspaceKind::Team);
        $file = $this->createFile($user, $workspace->id);

        $data = $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$file->id.'?workspace_id='.$workspace->id)
            ->assertOk()
            ->json('data');

        $this->assertSame('team', $data['workspace']['kind']);
        $this->assertArrayNotHasKey('microstock', $data);
    }

    public function test_microstock_workspace_file_payload_includes_microstock(): void
    {
        $user = $this->userWithRole('User');
        $workspace = $this->createWorkspace($user, WorkspaceKind::Microstock);
        $file = $this->createFile($user, $workspace->id);

        AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'billing_user_id' => $user->id,
            'title' => 'Sunset over rice terraces',
            'keywords' => ['sunset', 'bali'],
            'status' => MetadataStatus::Finalized,
        ]);

        $data = $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$file->id.'?workspace_id='.$workspace->id)
            ->assertOk()
            ->json('data');

        $this->assertSame('microstock', $data['workspace']['kind']);
        $this->assertArrayHasKey('microstock', $data);
        $this->assertSame('Sunset over rice terraces', $data['microstock']['title']);
        $this->assertSame(['sunset', 'bali'], $data['microstock']['keywords']);
        $this->assertSame('finalized', $data['microstock']['status']);
    }

    public function test_browse_microstock_workspace_still_lists_microstock_payload(): void
    {
        $user = $this->userWithRole('User');
        $workspace = $this->createWorkspace($user, WorkspaceKind::Microstock);
        $file = $this->createFile($user, $workspace->id);

        AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'billing_user_id' => $user->id,
            'title' => 'Studio product shot',
            'keywords' => ['product'],
            'status' => MetadataStatus::Finalized,
        ]);

        $files = $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/browse?workspace_id='.$workspace->id)
            ->assertOk()
            ->json('data.files');

        $this->assertCount(1, $files);
        $this->assertSame('microstock', $files[0]['workspace']['kind']);
        $this->assertSame('Studio product shot', $files[0]['microstock']['title']);
    }

    public function test_browse_team_workspace_files_have_no_microstock_key(): void
    {
        $user = $this->userWithRole('User');
        $workspace = $this->createWorkspace($user, WorkspaceKind::Team);
        $this->createFile($user, $workspace->id);

        $files = $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/browse?workspace_id='.$workspace->id)
            ->assertOk()
            ->json('data.files');

        $this->assertCount(1, $files);
        $this->assertSame('team', $files[0]['workspace']['kind']);
        $this->assertArrayNotHasKey('microstock', $files[0]);
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
