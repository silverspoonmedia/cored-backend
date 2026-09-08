<?php

namespace Tests\Domain\Archivd\Unit\Services;

use App\Domain\Archivd\Enums\WorkspaceKind;
use App\Domain\Archivd\Jobs\GenerateFileVariants;
use App\Domain\Archivd\Models\FileUpload;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\Workspace;
use App\Domain\Archivd\Services\DirectUploadService;
use App\Domain\Archivd\Support\ObjectKeyResolver;
use App\Domain\Identity\Models\User;
use Database\Seeders\ArchivdRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DirectUploadServiceVariantDispatchTest extends TestCase
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
            'archivd.allowed_mime_prefixes' => ['image/'],
            'archivd.upload_proxy.base_url' => '',
            'archivd.upload_proxy.token' => '',
            'archivd.upload_proxy.disk_targets' => [],
        ]);
    }

    public function test_complete_dispatches_variant_job_for_microstock_image(): void
    {
        Bus::fake([GenerateFileVariants::class]);

        $user = $this->userWithRole('User');
        $workspace = Workspace::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Microstock',
            'kind' => WorkspaceKind::Microstock,
            'status' => 'active',
        ]);

        $objectId = Str::lower(Str::random(ObjectKeyResolver::OBJECT_ID_LENGTH));
        $objectKey = app(ObjectKeyResolver::class)->objectKey($user->id, $objectId);

        $upload = FileUpload::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'folder_id' => null,
            'disk' => 'local',
            'path' => $objectId,
            's3_upload_id' => null,
            'strategy' => FileUpload::STRATEGY_SINGLE,
            'original_name' => 'photo.jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
            'status' => FileUpload::STATUS_PENDING,
            'parts' => [],
            'expires_at' => Carbon::now()->addHour(),
        ]);

        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put($objectKey, str_repeat('x', 2048));

        $result = app(DirectUploadService::class)->complete($user, $upload->id, null);

        $this->assertInstanceOf(HostedFile::class, $result['file']);
        Bus::assertDispatched(GenerateFileVariants::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
