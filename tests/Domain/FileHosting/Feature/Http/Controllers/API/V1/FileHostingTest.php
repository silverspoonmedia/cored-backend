<?php

namespace Tests\Domain\FileHosting\Feature\Http\Controllers\API\V1;

use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Domain\Identity\Models\User;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Fakes\FakeFileHostingDirectUploadService;
use Tests\TestCase;

class FileHostingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);

        config([
            'file-hosting.disk' => 'local',
            'file-hosting.default_quota_bytes' => 1024 * 1024,
            'file-hosting.allowed_mime_prefixes' => ['image/', 'application/', 'text/'],
        ]);

        Storage::fake('local');

        $this->app->instance(
            DirectUploadService::class,
            $this->app->make(FakeFileHostingDirectUploadService::class),
        );
    }

    public function test_user_can_create_nested_folder_and_browse_breadcrumb(): void
    {
        $user = $this->userWithRole('User');

        $root = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/folders', ['name' => 'Docs'])
            ->assertCreated()
            ->json('data');

        $child = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/folders', [
                'name' => 'Invoices',
                'parent_id' => $root['id'],
            ])
            ->assertCreated()
            ->json('data');

        $browse = $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/browse?folder_id='.$child['id'])
            ->assertOk()
            ->json('data');

        $this->assertSame($user->id, $browse['owner_id']);
        $this->assertCount(3, $browse['breadcrumb']);
        $this->assertNull($browse['breadcrumb'][0]['id']);
        $this->assertSame('Docs', $browse['breadcrumb'][1]['name']);
        $this->assertSame('Invoices', $browse['breadcrumb'][2]['name']);
    }

    public function test_user_upload_complete_creates_file_under_owner_prefix(): void
    {
        $user = $this->userWithRole('User');

        $init = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'report.pdf',
                'mime' => 'application/pdf',
                'size' => 1024,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertStringStartsWith(
            'file-hosting/'.$user->id.'/',
            $init['path'],
        );

        $complete = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads/'.$init['upload_id'].'/complete', [
                'parts' => [['part_number' => 1, 'etag' => 'abc', 'size' => 1024]],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('report.pdf', $complete['file']['name']);
        $this->assertSame($user->id, $complete['file']['user_id']);
        $this->assertNull($complete['file']['folder_id']);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/browse')
            ->assertOk()
            ->assertJsonFragment(['name' => 'report.pdf']);
    }

    public function test_user_cannot_browse_peer_tree(): void
    {
        $alice = $this->userWithRole('User');
        $bob = $this->userWithRole('User');

        Folder::query()->create([
            'user_id' => $alice->id,
            'parent_id' => null,
            'name' => 'Alice Only',
            'order' => 1,
        ]);

        $this->actingAsJwt($bob)
            ->getJson('/api/v1/file-hosting/browse?user_id='.$alice->id)
            ->assertStatus(403);
    }

    public function test_admin_can_browse_user_tree_but_user_cannot_browse_admin(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('User');

        Folder::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'User Docs',
            'order' => 1,
        ]);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/file-hosting/browse?user_id='.$user->id)
            ->assertOk()
            ->assertJsonFragment(['name' => 'User Docs']);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/browse?user_id='.$admin->id)
            ->assertStatus(403);
    }

    public function test_move_folder_into_self_is_rejected(): void
    {
        $user = $this->userWithRole('User');

        $folder = Folder::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Rootish',
            'order' => 1,
        ]);

        $child = Folder::query()->create([
            'user_id' => $user->id,
            'parent_id' => $folder->id,
            'name' => 'Child',
            'order' => 1,
        ]);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/folders/'.$folder->id.'/move', [
                'parent_id' => $child->id,
            ])
            ->assertStatus(422);
    }

    public function test_soft_delete_restore_and_force_delete_file(): void
    {
        $user = $this->userWithRole('User');

        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'gone.txt',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/gone.txt',
            'size' => 10,
            'mime' => 'text/plain',
        ]);

        Storage::disk('local')->put($file->path, 'x');

        $this->actingAsJwt($user)
            ->deleteJson('/api/v1/file-hosting/files/'.$file->id)
            ->assertOk();

        $this->assertSoftDeleted('file_hosting_files', ['id' => $file->id]);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/trash')
            ->assertOk()
            ->assertJsonFragment(['name' => 'gone.txt']);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/files/'.$file->id.'/restore')
            ->assertOk();

        $this->assertDatabaseHas('file_hosting_files', [
            'id' => $file->id,
            'deleted_at' => null,
        ]);

        $this->actingAsJwt($user)
            ->deleteJson('/api/v1/file-hosting/files/'.$file->id)
            ->assertOk();

        $this->actingAsJwt($user)
            ->deleteJson('/api/v1/file-hosting/files/'.$file->id.'/force')
            ->assertOk();

        $this->assertDatabaseMissing('file_hosting_files', ['id' => $file->id]);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_search_is_scoped_to_owner(): void
    {
        $alice = $this->userWithRole('User');
        $bob = $this->userWithRole('User');

        HostedFile::query()->create([
            'user_id' => $alice->id,
            'folder_id' => null,
            'name' => 'secret-alice.pdf',
            'disk' => 'local',
            'path' => 'file-hosting/'.$alice->id.'/a.pdf',
            'size' => 1,
            'mime' => 'application/pdf',
        ]);

        HostedFile::query()->create([
            'user_id' => $bob->id,
            'folder_id' => null,
            'name' => 'secret-bob.pdf',
            'disk' => 'local',
            'path' => 'file-hosting/'.$bob->id.'/b.pdf',
            'size' => 1,
            'mime' => 'application/pdf',
        ]);

        $this->actingAsJwt($bob)
            ->getJson('/api/v1/file-hosting/search?q=secret')
            ->assertOk()
            ->assertJsonFragment(['name' => 'secret-bob.pdf'])
            ->assertJsonMissing(['name' => 'secret-alice.pdf']);
    }

    public function test_quota_exceeded_on_init(): void
    {
        $user = $this->userWithRole('User');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'big.bin',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/big.bin',
            'size' => 1024 * 1024,
            'mime' => 'application/octet-stream',
        ]);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'extra.txt',
                'mime' => 'text/plain',
                'size' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('api/file-hosting.quota_exceeded'));
    }

    public function test_quota_endpoint_reports_usage(): void
    {
        $user = $this->userWithRole('User');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'a.txt',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/a.txt',
            'size' => 100,
            'mime' => 'text/plain',
        ]);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/quota')
            ->assertOk()
            ->assertJsonPath('data.used_bytes', 100)
            ->assertJsonPath('data.quota_bytes', 1024 * 1024);
    }

    public function test_file_hosting_role_seeder_product_domain_kind(): void
    {
        $this->assertDatabaseHas('roles', [
            'name' => 'File Hosting Admin',
            'domain_kind' => 'product',
            'level' => 1100,
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'view any file hosting resource',
            'domain_kind' => 'product',
        ]);
    }

    public function test_rename_file_enforces_sibling_uniqueness(): void
    {
        $user = $this->userWithRole('User');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'one.txt',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/one.txt',
            'size' => 1,
            'mime' => 'text/plain',
        ]);

        $two = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'two.txt',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/two.txt',
            'size' => 1,
            'mime' => 'text/plain',
        ]);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/files/'.$two->id.'/rename', [
                'name' => 'one.txt',
            ])
            ->assertStatus(422);
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
