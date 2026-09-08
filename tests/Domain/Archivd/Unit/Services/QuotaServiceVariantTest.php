<?php

namespace Tests\Domain\Archivd\Unit\Services;

use App\Domain\Archivd\Enums\FileVariantKind;
use App\Domain\Archivd\Enums\FileVariantStatus;
use App\Domain\Archivd\Models\FileVariant;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Identity\Models\User;
use Database\Seeders\ArchivdRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class QuotaServiceVariantTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);
        config(['archivd.default_quota_bytes' => 10 * 1024 * 1024]);
    }

    public function test_committed_bytes_includes_ready_variants(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'workspace_id' => null,
            'folder_id' => null,
            'name' => 'photo.jpg',
            'mime' => 'image/jpeg',
            'size' => 1000,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'visibility' => 'private',
        ]);

        FileVariant::query()->create([
            'file_id' => $file->id,
            'kind' => FileVariantKind::Ai512,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'size' => 250,
            'max_px' => 512,
            'mime' => 'image/jpeg',
            'status' => FileVariantStatus::Ready,
        ]);

        FileVariant::query()->create([
            'file_id' => $file->id,
            'kind' => FileVariantKind::Ai1024,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'size' => 500,
            'max_px' => 1024,
            'mime' => 'image/jpeg',
            'status' => FileVariantStatus::Pending,
        ]);

        $this->assertSame(1250, app(QuotaService::class)->committedBytes($user->id));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
