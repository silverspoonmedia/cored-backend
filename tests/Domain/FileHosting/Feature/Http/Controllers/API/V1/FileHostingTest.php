<?php

namespace Tests\Domain\FileHosting\Feature\Http\Controllers\API\V1;

use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Domain\FileHosting\Services\SignedDownloadService;
use App\Domain\Identity\Models\User;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Fakes\FakeFileHostingDirectUploadService;
use Tests\Fakes\FakeSignedDownloadService;
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
            'file-hosting.signed_download_ttl_minutes' => 15,
            'file-hosting.signed_download_cache_margin_seconds' => 60,
        ]);

        Storage::fake('local');

        $this->app->instance(
            DirectUploadService::class,
            $this->app->make(FakeFileHostingDirectUploadService::class),
        );
        $this->app->instance(
            SignedDownloadService::class,
            $this->app->make(FakeSignedDownloadService::class),
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

        // Object key uses random segment, not the original basename slug.
        $this->assertDoesNotMatchRegularExpression('/report\.pdf$/i', $init['path']);
        $this->assertMatchesRegularExpression('/\/[a-z0-9]{40}\.pdf$/', $init['path']);

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

    public function test_upload_complete_auto_renames_duplicate_display_name(): void
    {
        $user = $this->userWithRole('User');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'A.zip',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/existing/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.zip',
            'size' => 100,
            'mime' => 'application/zip',
        ]);

        $init = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'A.zip',
                'mime' => 'application/zip',
                'size' => 1024,
            ])
            ->assertCreated()
            ->json('data');

        $complete = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads/'.$init['upload_id'].'/complete', [
                'parts' => [['part_number' => 1, 'etag' => 'abc', 'size' => 1024]],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('A (2).zip', $complete['file']['name']);
    }

    public function test_upload_complete_sequence_skips_to_max_plus_one(): void
    {
        $user = $this->userWithRole('User');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'A.zip',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/a/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.zip',
            'size' => 1,
            'mime' => 'application/zip',
        ]);
        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'A (3).zip',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/b/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.zip',
            'size' => 1,
            'mime' => 'application/zip',
        ]);

        $init = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'A.zip',
                'mime' => 'application/zip',
                'size' => 1024,
            ])
            ->assertCreated()
            ->json('data');

        $complete = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads/'.$init['upload_id'].'/complete', [
                'parts' => [['part_number' => 1, 'etag' => 'abc', 'size' => 1024]],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('A (4).zip', $complete['file']['name']);
    }

    public function test_upload_complete_reuses_base_name_after_soft_delete(): void
    {
        $user = $this->userWithRole('User');

        $old = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'A.zip',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/old/cccccccccccccccccccccccccccccccccccccccc.zip',
            'size' => 1,
            'mime' => 'application/zip',
        ]);
        $old->delete();

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'A (3).zip',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/c3/dddddddddddddddddddddddddddddddddddddddd.zip',
            'size' => 1,
            'mime' => 'application/zip',
        ]);

        $init = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'A.zip',
                'mime' => 'application/zip',
                'size' => 1024,
            ])
            ->assertCreated()
            ->json('data');

        $first = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads/'.$init['upload_id'].'/complete', [
                'parts' => [['part_number' => 1, 'etag' => 'abc', 'size' => 1024]],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('A.zip', $first['file']['name']);

        $init2 = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'A.zip',
                'mime' => 'application/zip',
                'size' => 1024,
            ])
            ->assertCreated()
            ->json('data');

        $second = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads/'.$init2['upload_id'].'/complete', [
                'parts' => [['part_number' => 1, 'etag' => 'def', 'size' => 1024]],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('A (4).zip', $second['file']['name']);
    }

    public function test_upload_init_allows_same_display_name_in_different_folder(): void
    {
        $user = $this->userWithRole('User');

        $folder = Folder::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Other',
        ]);

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'report.pdf',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/existing/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.pdf',
            'size' => 100,
            'mime' => 'application/pdf',
        ]);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'report.pdf',
                'mime' => 'application/pdf',
                'size' => 1024,
                'folder_id' => $folder->id,
            ])
            ->assertCreated();
    }

    public function test_show_increments_view_count(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'notes.txt',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/abc/notes.txt',
            'size' => 10,
            'mime' => 'text/plain',
            'view_count' => 0,
            'download_count' => 0,
        ]);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$file->id)
            ->assertOk()
            ->assertJsonPath('data.view_count', 1);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$file->id)
            ->assertOk()
            ->assertJsonPath('data.view_count', 2);

        $this->assertNotNull($file->fresh()->last_viewed_at);
    }

    public function test_download_caches_signed_url_until_expiry(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'cache-me.pdf',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/xyz/random.pdf',
            'size' => 10,
            'mime' => 'application/pdf',
        ]);

        $first = $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$file->id.'/download')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($first['url']);
        $this->assertNotEmpty($first['expires_at']);
        $this->assertSame(1, $file->fresh()->download_count);

        $second = $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$file->id.'/download')
            ->assertOk()
            ->json('data');

        $this->assertSame($first['url'], $second['url']);
        $this->assertSame($first['expires_at'], $second['expires_at']);
        $this->assertSame(2, $file->fresh()->download_count);

        Carbon::setTestNow(Carbon::parse($first['expires_at'])->addMinute());

        $third = $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$file->id.'/download')
            ->assertOk()
            ->json('data');

        $this->assertNotSame($first['url'], $third['url']);
        $this->assertSame(3, $file->fresh()->download_count);

        Carbon::setTestNow();
    }

    public function test_rename_file_updates_name_and_clears_signed_cache(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'old-name.txt',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/r/old.txt',
            'size' => 1,
            'mime' => 'text/plain',
            'signed_download_url' => 'https://fake-storage.test/stale',
            'signed_download_expires_at' => now()->addHour(),
        ]);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/files/'.$file->id.'/rename', [
                'name' => 'new-name.txt',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'new-name.txt');

        $fresh = $file->fresh();
        $this->assertSame('new-name.txt', $fresh->name);
        $this->assertNull($fresh->signed_download_url);
        $this->assertNull($fresh->signed_download_expires_at);
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

    public function test_search_is_case_insensitive(): void
    {
        $user = $this->userWithRole('User');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'Aku-Ini.pdf',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/a.pdf',
            'size' => 1,
            'mime' => 'application/pdf',
        ]);

        // Lowercase query matches mixed-case filename.
        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/search?q=aku')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Aku-Ini.pdf']);

        // Uppercase query matches same file.
        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/search?q=AKU')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Aku-Ini.pdf']);
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

    public function test_private_file_public_routes_return_not_found(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'secret.txt',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/secret.txt',
            'size' => 1,
            'mime' => 'text/plain',
            'visibility' => 'private',
        ]);

        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id)
            ->assertStatus(404);

        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id.'/download')
            ->assertStatus(404);
    }

    public function test_anyone_with_link_file_is_publicly_readable_and_downloadable(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'shared.pdf',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/shared.pdf',
            'size' => 10,
            'mime' => 'application/pdf',
            'visibility' => 'private',
        ]);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/files/'.$file->id.'/visibility', [
                'visibility' => 'anyone_with_link',
            ])
            ->assertOk()
            ->assertJsonPath('data.visibility', 'anyone_with_link')
            ->assertJsonPath('data.share_url', route('file-hosting.public.files.show', $file->id));

        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'shared.pdf')
            ->assertJsonPath('data.view_count', 1);

        $download = $this->getJson('/api/v1/file-hosting/public/files/'.$file->id.'/download')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($download['url']);
        $this->assertNotEmpty($download['expires_at']);
        $this->assertSame(1, $file->fresh()->download_count);
    }

    public function test_folder_anyone_with_link_inherits_to_private_child_file(): void
    {
        $user = $this->userWithRole('User');
        $folder = Folder::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Shared Docs',
            'order' => 1,
            'visibility' => 'anyone_with_link',
        ]);

        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => $folder->id,
            'name' => 'inside.txt',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/inside.txt',
            'size' => 5,
            'mime' => 'text/plain',
            'visibility' => 'private',
        ]);

        $browse = $this->getJson('/api/v1/file-hosting/public/folders/'.$folder->id)
            ->assertOk()
            ->json('data');

        $this->assertSame($folder->id, $browse['folder']['id']);
        $this->assertCount(1, $browse['files']);
        $this->assertSame('inside.txt', $browse['files'][0]['name']);

        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'inside.txt');

        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id.'/download')
            ->assertOk()
            ->assertJsonStructure(['data' => ['url', 'expires_at']]);
    }

    public function test_owner_can_set_folder_visibility_private_again(): void
    {
        $user = $this->userWithRole('User');
        $folder = Folder::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Toggle',
            'order' => 1,
            'visibility' => 'anyone_with_link',
        ]);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/folders/'.$folder->id.'/visibility', [
                'visibility' => 'private',
            ])
            ->assertOk()
            ->assertJsonPath('data.visibility', 'private')
            ->assertJsonPath('data.share_url', null);

        $this->getJson('/api/v1/file-hosting/public/folders/'.$folder->id)
            ->assertStatus(404);
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

    public function test_init_accepts_disk_param_and_persists_resolved_disk(): void
    {
        config(['file-hosting.regions' => ['sea' => 'local', 'us' => 'local']]);

        $user = $this->userWithRole('User');

        $init = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'report.pdf',
                'mime' => 'application/pdf',
                'size' => 1024,
                'disk' => 'sea',
            ])
            ->assertCreated()
            ->json('data');

        $upload = FileUpload::query()->whereKey($init['upload_id'])->firstOrFail();
        $this->assertSame('local', $upload->disk);
    }

    public function test_init_falls_back_to_default_disk_when_disk_missing(): void
    {
        config(['file-hosting.regions' => ['sea' => 'local']]);

        $user = $this->userWithRole('User');

        $init = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'report.pdf',
                'mime' => 'application/pdf',
                'size' => 1024,
            ])
            ->assertCreated()
            ->json('data');

        $upload = FileUpload::query()->whereKey($init['upload_id'])->firstOrFail();
        $this->assertSame((string) config('file-hosting.disk'), $upload->disk);
    }

    public function test_init_rejects_disk_not_in_regions(): void
    {
        config(['file-hosting.regions' => ['sea' => 'local']]);

        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'report.pdf',
                'mime' => 'application/pdf',
                'size' => 1024,
                'disk' => 'mars',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['disk']);
    }

    public function test_init_rejects_disabled_region_for_upload(): void
    {
        config([
            'file-hosting.regions' => ['sea' => 'local', 'us' => 'local'],
            'file-hosting.disabled_regions' => ['us'],
        ]);

        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'report.pdf',
                'mime' => 'application/pdf',
                'size' => 1024,
                'disk' => 'us',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['disk']);
    }

    public function test_browse_response_includes_region_key_for_files(): void
    {
        config(['file-hosting.regions' => ['sea' => 'local']]);

        $user = $this->userWithRole('User');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'pinned.pdf',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/pinned.pdf',
            'size' => 10,
            'mime' => 'application/pdf',
        ]);

        $browse = $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/browse')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($browse['files']);
        $this->assertSame('sea', $browse['files'][0]['region']);
    }

    public function test_disabled_region_still_serves_download(): void
    {
        config([
            'file-hosting.regions' => ['sea' => 'local'],
            'file-hosting.disabled_regions' => ['sea'],
        ]);

        $user = $this->userWithRole('User');

        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'legacy.txt',
            'disk' => 'local',
            'path' => 'file-hosting/'.$user->id.'/legacy.txt',
            'size' => 4,
            'mime' => 'text/plain',
        ]);

        Storage::disk('local')->put($file->path, 'data');

        // Disabled region blocks new uploads but not downloads.
        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$file->id.'/download')
            ->assertOk();
    }

    public function test_bulk_set_file_visibility(): void
    {
        $user = $this->userWithRole('User');
        $ids = [];

        for ($i = 0; $i < 3; $i++) {
            $file = HostedFile::query()->create([
                'user_id' => $user->id,
                'folder_id' => null,
                'name' => 'bulk-'.$i.'.txt',
                'disk' => 'local',
                'path' => 'file-hosting/'.$user->id.'/bulk-'.$i.'.txt',
                'size' => 1,
                'mime' => 'text/plain',
            ]);
            $ids[] = $file->id;
        }

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/files/bulk-visibility', [
                'ids' => $ids,
                'visibility' => 'anyone_with_link',
            ])
            ->assertOk()
            ->assertJsonPath('data.affected', 3);

        foreach ($ids as $id) {
            $this->assertSame('anyone_with_link', HostedFile::query()->whereKey($id)->value('visibility'));
        }
    }

    public function test_bulk_set_file_visibility_scoped_to_owner(): void
    {
        $alice = $this->userWithRole('User');
        $bob = $this->userWithRole('User');

        $aliceFile = HostedFile::query()->create([
            'user_id' => $alice->id,
            'folder_id' => null,
            'name' => 'alice.pdf',
            'disk' => 'local',
            'path' => 'file-hosting/'.$alice->id.'/alice.pdf',
            'size' => 1,
            'mime' => 'application/pdf',
        ]);

        $this->actingAsJwt($bob)
            ->postJson('/api/v1/file-hosting/files/bulk-visibility', [
                'ids' => [$aliceFile->id],
                'visibility' => 'anyone_with_link',
            ])
            ->assertOk();

        // Alice's file untouched — bulk is owner-scoped.
        $this->assertSame('private', $aliceFile->fresh()->visibility);
    }

    public function test_bulk_delete_files(): void
    {
        $user = $this->userWithRole('User');
        $ids = [];

        for ($i = 0; $i < 3; $i++) {
            $file = HostedFile::query()->create([
                'user_id' => $user->id,
                'folder_id' => null,
                'name' => 'del-'.$i.'.txt',
                'disk' => 'local',
                'path' => 'file-hosting/'.$user->id.'/del-'.$i.'.txt',
                'size' => 1,
                'mime' => 'text/plain',
            ]);
            $ids[] = $file->id;
        }

        $this->actingAsJwt($user)
            ->deleteJson('/api/v1/file-hosting/files/bulk', ['ids' => $ids])
            ->assertOk()
            ->assertJsonPath('data.affected', 3);

        foreach ($ids as $id) {
            $this->assertNotNull(HostedFile::withTrashed()->whereKey($id)->value('deleted_at'));
        }
    }

    public function test_bulk_set_folder_visibility(): void
    {
        $user = $this->userWithRole('User');
        $ids = [];

        for ($i = 0; $i < 2; $i++) {
            $folder = Folder::query()->create([
                'user_id' => $user->id,
                'parent_id' => null,
                'name' => 'bulk-folder-'.$i,
            ]);
            $ids[] = $folder->id;
        }

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/folders/bulk-visibility', [
                'ids' => $ids,
                'visibility' => 'anyone_with_link',
            ])
            ->assertOk()
            ->assertJsonPath('data.affected', 2);

        foreach ($ids as $id) {
            $this->assertSame('anyone_with_link', Folder::query()->whereKey($id)->value('visibility'));
        }
    }

    public function test_bulk_delete_folders(): void
    {
        $user = $this->userWithRole('User');
        $ids = [];

        for ($i = 0; $i < 2; $i++) {
            $folder = Folder::query()->create([
                'user_id' => $user->id,
                'parent_id' => null,
                'name' => 'del-folder-'.$i,
            ]);
            $ids[] = $folder->id;
        }

        $this->actingAsJwt($user)
            ->deleteJson('/api/v1/file-hosting/folders/bulk', ['ids' => $ids])
            ->assertOk()
            ->assertJsonPath('data.affected', 2);

        foreach ($ids as $id) {
            $this->assertNotNull(Folder::withTrashed()->whereKey($id)->value('deleted_at'));
        }
    }

    public function test_bulk_action_validates_empty_ids(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/files/bulk-visibility', [
                'ids' => [],
                'visibility' => 'private',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);
    }
}
