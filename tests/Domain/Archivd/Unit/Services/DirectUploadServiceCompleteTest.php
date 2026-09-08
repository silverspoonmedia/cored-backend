<?php

namespace Tests\Domain\Archivd\Unit\Services;

use App\Domain\Archivd\Models\FileUpload;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\DirectUploadService;
use App\Domain\Archivd\Support\ObjectKeyResolver;
use App\Domain\Identity\Models\User;
use Database\Seeders\ArchivdRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Real DirectUploadService::complete() (not Fake) — regression for Collaborative Works
 * wiring `created_by` / activity without capturing `$actor` in the transaction closure.
 */
class DirectUploadServiceCompleteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);

        config([
            'archivd.disk' => 'local',
            'archivd.directory' => 'archivd',
            'archivd.default_quota_bytes' => 10 * 1024 * 1024,
            'archivd.allowed_mime_prefixes' => ['image/', 'application/', 'text/'],
            'archivd.upload_proxy.base_url' => '',
            'archivd.upload_proxy.token' => '',
            'archivd.upload_proxy.disk_targets' => [],
        ]);

        Storage::fake('local');
    }

    public function test_complete_single_sets_created_by_actor(): void
    {
        $user = $this->userWithRole('User');
        $objectId = Str::lower(Str::random(ObjectKeyResolver::OBJECT_ID_LENGTH));
        $objectKey = app(ObjectKeyResolver::class)->objectKey($user->id, $objectId);

        $upload = FileUpload::query()->create([
            'user_id' => $user->id,
            'workspace_id' => null,
            'folder_id' => null,
            'disk' => 'local',
            'path' => $objectId,
            's3_upload_id' => null,
            'strategy' => FileUpload::STRATEGY_SINGLE,
            'original_name' => 'report.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'status' => FileUpload::STATUS_PENDING,
            'parts' => [],
            'expires_at' => Carbon::now()->addHour(),
        ]);

        Storage::disk('local')->put($objectKey, str_repeat('x', 1024));

        $result = app(DirectUploadService::class)->complete($user, $upload->id, null);

        $file = $result['file'];
        $this->assertInstanceOf(HostedFile::class, $file);
        $this->assertSame($user->id, $file->created_by);
        $this->assertSame($user->id, $file->user_id);
        $this->assertSame('report.pdf', $file->name);
        $this->assertSame($objectId, $file->path);
        $this->assertSame(FileUpload::STATUS_COMPLETED, $result['upload']->status);
        Storage::disk('local')->assertExists($objectKey);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
