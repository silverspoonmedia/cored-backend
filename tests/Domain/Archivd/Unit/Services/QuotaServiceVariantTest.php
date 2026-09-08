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
use Illuminate\Support\Facades\DB;
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

    public function test_committed_bytes_excludes_variants_of_soft_deleted_files(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'workspace_id' => null,
            'folder_id' => null,
            'name' => 'gone.jpg',
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

        $file->delete();

        $this->assertSame(0, app(QuotaService::class)->committedBytes($user->id));
    }

    public function test_assert_can_accept_reuses_storage_state_memo(): void
    {
        $user = $this->userWithRole('User');
        $quota = app(QuotaService::class);

        $entitlementQueries = 0;
        DB::listen(function ($query) use (&$entitlementQueries): void {
            if (str_contains((string) $query->sql, 'service_entitlements')) {
                $entitlementQueries++;
            }
        });

        $quota->storageStateFor($user->id);
        $first = $entitlementQueries;
        $this->assertGreaterThan(0, $first);

        $quota->assertCanAccept($user->id, 1);
        $this->assertSame($first, $entitlementQueries);
    }

    public function test_storage_state_for_is_memoized_on_the_scoped_instance(): void
    {
        $user = $this->userWithRole('User');
        $quota = app(QuotaService::class);

        $entitlementQueries = 0;
        DB::listen(function ($query) use (&$entitlementQueries): void {
            if (str_contains((string) $query->sql, 'service_entitlements')) {
                $entitlementQueries++;
            }
        });

        $this->assertSame('free', $quota->storageStateFor($user->id));
        $first = $entitlementQueries;
        $this->assertGreaterThan(0, $first);

        $this->assertSame('free', $quota->storageStateFor($user->id));
        $this->assertSame($first, $entitlementQueries);
        $this->assertSame($quota, app(QuotaService::class));
    }

    public function test_usage_twice_does_not_repeat_paid_grace_probes(): void
    {
        $user = $this->userWithRole('User');
        $quota = app(QuotaService::class);

        $probeQueries = 0;
        DB::listen(function ($query) use (&$probeQueries): void {
            $sql = (string) $query->sql;
            if (str_contains($sql, 'service_entitlements') && str_contains($sql, 'storage_bytes')) {
                $probeQueries++;
            }
        });

        $first = $quota->usage($user->id);
        $this->assertGreaterThan(0, $probeQueries);
        $count = $probeQueries;

        $second = $quota->usage($user->id);
        $this->assertSame($count, $probeQueries);
        $this->assertSame($first['used_bytes'], $second['used_bytes']);
        $this->assertSame($first['state'], $second['state']);
        $this->assertSame($first['owned_workspace_limit'], $second['owned_workspace_limit']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
