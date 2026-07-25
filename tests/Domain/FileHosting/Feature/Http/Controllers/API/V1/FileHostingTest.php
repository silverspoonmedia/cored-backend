<?php

namespace Tests\Domain\FileHosting\Feature\Http\Controllers\API\V1;

use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Domain\FileHosting\Services\SignedDownloadService;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
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

        // DB path is object id only (not full S3 key).
        $this->assertMatchesRegularExpression('/^[a-z0-9]{40}$/', $init['path']);
        $this->assertStringNotContainsString('/', $init['path']);
        $this->assertDoesNotMatchRegularExpression('/report/i', $init['path']);

        $complete = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads/'.$init['upload_id'].'/complete', [
                'parts' => [['part_number' => 1, 'etag' => 'abc', 'size' => 1024]],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('report.pdf', $complete['file']['name']);
        $this->assertSame($user->id, $complete['file']['user_id']);
        $this->assertNull($complete['file']['folder_id']);
        $this->assertSame($init['path'], $complete['file']['path']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{40}$/', $complete['file']['path']);
        Storage::disk('local')->assertExists('file-hosting/'.$user->id.'/'.$complete['file']['path']);

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
            'path' => 'c470d52e05c90343d8980849afc5ccd4df3a2af1',
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
            'path' => 'bd697fe15bb9d3ae0c133a431e76056e5a438309',
            'size' => 1,
            'mime' => 'application/zip',
        ]);
        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'A (3).zip',
            'disk' => 'local',
            'path' => '1d2208eee0c5db3a3abae1f7d2ea6b8abeabed28',
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
            'path' => 'ac5f00cd72a1cb45598e2cc0b61d32e8647cbdba',
            'size' => 1,
            'mime' => 'application/zip',
        ]);
        $old->delete();

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'A (3).zip',
            'disk' => 'local',
            'path' => 'a6afb8f143b98d597d9a24f90b3496520a194219',
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
            'path' => 'e9f56ffdeb91c49855d86f8ccfd73ff1d069723a',
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
            'path' => '479885d63d1e19cb0f52fe90af0a842691809c1e',
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
            'path' => '5dccef45920d9faf8fda1986927ff2767e8a4a24',
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
            'path' => '32ae4296236ce03a3b5c1b006cfa765334e238c7',
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

    public function test_move_file_auto_renames_on_sibling_collision(): void
    {
        $user = $this->userWithRole('User');

        $target = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/folders', ['name' => 'Target'])
            ->assertCreated()
            ->json('data');

        $source = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/folders', ['name' => 'Source'])
            ->assertCreated()
            ->json('data');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => $target['id'],
            'name' => 'A.zip',
            'disk' => 'local',
            'path' => substr(hash('sha256', 'target-a'), 0, 40),
            'size' => 10,
            'mime' => 'application/zip',
        ]);

        $moving = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => $source['id'],
            'name' => 'A.zip',
            'disk' => 'local',
            'path' => substr(hash('sha256', 'source-a'), 0, 40),
            'size' => 20,
            'mime' => 'application/zip',
            'signed_download_url' => 'https://example.test/old-signed',
            'signed_download_expires_at' => now()->addHour(),
        ]);

        $moved = $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/files/'.$moving->id.'/move', [
                'folder_id' => $target['id'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame($target['id'], $moved['folder_id']);
        $this->assertSame('A (2).zip', $moved['name']);
        $this->assertNull($moving->fresh()->signed_download_url);
        $this->assertSame('A.zip', HostedFile::query()
            ->where('folder_id', $target['id'])
            ->where('path', substr(hash('sha256', 'target-a'), 0, 40))
            ->value('name'));
    }

    public function test_move_file_keeps_name_when_free(): void
    {
        $user = $this->userWithRole('User');

        $target = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/folders', ['name' => 'Target'])
            ->assertCreated()
            ->json('data');

        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'unique.pdf',
            'disk' => 'local',
            'path' => substr(hash('sha256', 'unique-pdf'), 0, 40),
            'size' => 10,
            'mime' => 'application/pdf',
        ]);

        $moved = $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/files/'.$file->id.'/move', [
                'folder_id' => $target['id'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame($target['id'], $moved['folder_id']);
        $this->assertSame('unique.pdf', $moved['name']);
    }

    public function test_move_folder_auto_renames_on_sibling_collision(): void
    {
        $user = $this->userWithRole('User');

        $parent = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/folders', ['name' => 'Parent'])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/folders', [
                'name' => 'Docs',
                'parent_id' => $parent['id'],
            ])
            ->assertCreated();

        $moving = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/folders', ['name' => 'Docs'])
            ->assertCreated()
            ->json('data');

        $moved = $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/folders/'.$moving['id'].'/move', [
                'parent_id' => $parent['id'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame($parent['id'], $moved['parent_id']);
        $this->assertSame('Docs (2)', $moved['name']);
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
            'path' => 'f6652577c6db6b8dc2719a7af938fccdcfbaf796',
            'size' => 10,
            'mime' => 'text/plain',
        ]);

        $objectKey = 'file-hosting/'.$user->id.'/'.$file->path;
        Storage::disk('local')->put($objectKey, 'x');

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
        Storage::disk('local')->assertMissing($objectKey);
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
            'path' => '55abfed1c28ec86c101066dde69d8fa74da72092',
            'size' => 1,
            'mime' => 'application/pdf',
        ]);

        HostedFile::query()->create([
            'user_id' => $bob->id,
            'folder_id' => null,
            'name' => 'secret-bob.pdf',
            'disk' => 'local',
            'path' => '41e52952442e648c489451c389951a05757f596e',
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
            'path' => '4c23630df751803f037fdbd7fb393e52f9ba8b91',
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
            'path' => 'dab81daa8455fd309df2acd6ef318f59cfe86040',
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
            'path' => 'e15cfbbd338856e01551aa4b949bc123b1c4ca49',
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
            'path' => '9d82e7cf74ef012b8e46a35e8db529cd7d9eac20',
            'size' => 1,
            'mime' => 'text/plain',
        ]);

        $two = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'two.txt',
            'disk' => 'local',
            'path' => 'b4203f23d4b297aadb73851b5989e8a0b0a03168',
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
            'path' => '9e64c04d1b2d18e6bfde2e27fe7e8c96c404582c',
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
        $this->grantPaidMain($user);
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'shared.pdf',
            'disk' => 'local',
            'path' => 'a17d56f8fe251b3a5587944f08e9b8fea2fc47e7',
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

    public function test_free_owner_cannot_enable_anyone_with_link(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'free-share.pdf',
            'disk' => 'local',
            'path' => 'b27d56f8fe251b3a5587944f08e9b8fea2fc47e7',
            'size' => 10,
            'mime' => 'application/pdf',
            'visibility' => 'private',
        ]);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/files/'.$file->id.'/visibility', [
                'visibility' => 'anyone_with_link',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('api/file-hosting.public_share_requires_paid'));

        $this->assertSame('private', $file->fresh()->visibility);
    }

    public function test_free_owner_link_file_is_not_publicly_readable(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'legacy-link.pdf',
            'disk' => 'local',
            'path' => 'c37d56f8fe251b3a5587944f08e9b8fea2fc47e7',
            'size' => 10,
            'mime' => 'application/pdf',
            'visibility' => 'anyone_with_link',
        ]);

        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id)
            ->assertStatus(404);

        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id.'/download')
            ->assertStatus(404);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$file->id)
            ->assertOk()
            ->assertJsonPath('data.visibility', 'anyone_with_link')
            ->assertJsonPath('data.share_url', null);
    }

    public function test_grace_owner_link_file_is_not_publicly_readable(): void
    {
        $user = $this->userWithRole('User');
        $this->grantGraceMain($user);
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'grace-link.pdf',
            'disk' => 'local',
            'path' => 'd47d56f8fe251b3a5587944f08e9b8fea2fc47e7',
            'size' => 10,
            'mime' => 'application/pdf',
            'visibility' => 'anyone_with_link',
        ]);

        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id)
            ->assertStatus(404);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/files/'.$file->id.'/visibility', [
                'visibility' => 'anyone_with_link',
            ])
            ->assertStatus(422);
    }

    public function test_folder_anyone_with_link_inherits_to_private_child_file(): void
    {
        $user = $this->userWithRole('User');
        $this->grantPaidMain($user);
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
            'path' => '35fa0dfc79df4c99fdbe71cebfab24881e7f6144',
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
        $this->grantPaidMain($user);
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

    private function grantPaidMain(User $user): void
    {
        $service = Service::query()->where('code', 'file-hosting.plan.1tb.monthly')->first()
            ?? Service::factory()->fileHostingMain1Tb()->create();

        ServiceEntitlement::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'service_id' => $service->id,
            ],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => 1,
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
            ],
        );
    }

    private function grantGraceMain(User $user): void
    {
        $service = Service::query()->where('code', 'file-hosting.plan.1tb.monthly')->first()
            ?? Service::factory()->fileHostingMain1Tb()->create();

        ServiceEntitlement::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'service_id' => $service->id,
            ],
            [
                'status' => EntitlementStatus::Grace,
                'quantity' => 1,
                'starts_at' => now()->subDays(40),
                'ends_at' => now()->subDay(),
            ],
        );
    }

    public function test_init_accepts_disk_param_and_persists_resolved_disk(): void
    {
        config(['file-hosting.regions' => ['ce' => 'local', 'us' => 'local']]);

        $user = $this->userWithRole('User');

        $init = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'report.pdf',
                'mime' => 'application/pdf',
                'size' => 1024,
                'disk' => 'ce',
            ])
            ->assertCreated()
            ->json('data');

        $upload = FileUpload::query()->whereKey($init['upload_id'])->firstOrFail();
        $this->assertSame('local', $upload->disk);
    }

    public function test_init_falls_back_to_default_disk_when_disk_missing(): void
    {
        config(['file-hosting.regions' => ['ce' => 'local']]);

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
        config(['file-hosting.regions' => ['ce' => 'local']]);

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
            'file-hosting.regions' => ['ce' => 'local', 'us' => 'local'],
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
        config(['file-hosting.regions' => ['ce' => 'local']]);

        $user = $this->userWithRole('User');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'pinned.pdf',
            'disk' => 'local',
            'path' => '9922fb348f00a3b5f38bab3332287ce11cedffba',
            'size' => 10,
            'mime' => 'application/pdf',
        ]);

        $browse = $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/browse')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($browse['files']);
        $this->assertSame('ce', $browse['files'][0]['region']);
    }

    public function test_disabled_region_still_serves_download(): void
    {
        config([
            'file-hosting.regions' => ['ce' => 'local'],
            'file-hosting.disabled_regions' => ['ce'],
        ]);

        $user = $this->userWithRole('User');

        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'legacy.txt',
            'disk' => 'local',
            'path' => 'e3458cd89b386db64aeae1b2329c66b589b977ef',
            'size' => 4,
            'mime' => 'text/plain',
        ]);

        Storage::disk('local')->put('file-hosting/'.$user->id.'/'.$file->path, 'data');

        // Disabled region blocks new uploads but not downloads.
        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$file->id.'/download')
            ->assertOk();
    }

    public function test_bulk_set_file_visibility(): void
    {
        $user = $this->userWithRole('User');
        $this->grantPaidMain($user);
        $ids = [];

        for ($i = 0; $i < 3; $i++) {
            $file = HostedFile::query()->create([
                'user_id' => $user->id,
                'folder_id' => null,
                'name' => 'bulk-'.$i.'.txt',
                'disk' => 'local',
                'path' => substr(hash('sha256', 'bulk-'.$i), 0, 40),
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
        $this->grantPaidMain($bob);

        $aliceFile = HostedFile::query()->create([
            'user_id' => $alice->id,
            'folder_id' => null,
            'name' => 'alice.pdf',
            'disk' => 'local',
            'path' => 'fab315e2f6e812e6e935debf135f53ceb60bd3a8',
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
                'path' => substr(hash('sha256', 'del-'.$i), 0, 40),
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
        $this->grantPaidMain($user);
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
