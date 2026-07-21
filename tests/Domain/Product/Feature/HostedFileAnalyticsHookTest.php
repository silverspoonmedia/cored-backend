<?php

namespace Tests\Domain\Product\Feature;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Support\Product\Models\ProductAnalytic;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Verifies the HostedFile Eloquent hooks update the per-(tier, metric) bucket
 * rows. Test users have no entitlement, so all writes land in the 'free' bucket.
 */
class HostedFileAnalyticsHookTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $activations;

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
        $this->activations = app(ProductActivationService::class);
    }

    public function test_create_file_increments_storage_and_file_count(): void
    {
        $user = $this->userWithRole('User');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'name' => 'a.txt',
            'disk' => 'local',
            'path' => '/a',
            'size' => 500,
            'mime' => 'text/plain',
        ]);

        $this->assertSame(500, $this->bucket('free', 'storage_used_bytes'));
        $this->assertSame(1, $this->bucket('free', 'file_count'));
    }

    public function test_soft_delete_decrements_counters(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'name' => 'a.txt',
            'disk' => 'local',
            'path' => '/a',
            'size' => 500,
            'mime' => 'text/plain',
        ]);

        $file->delete();

        $this->assertSame(0, $this->bucket('free', 'storage_used_bytes'));
        $this->assertSame(0, $this->bucket('free', 'file_count'));
    }

    public function test_restore_increments_counters(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'name' => 'a.txt',
            'disk' => 'local',
            'path' => '/a',
            'size' => 500,
            'mime' => 'text/plain',
        ]);
        $file->delete();

        $file->restore();

        $this->assertSame(500, $this->bucket('free', 'storage_used_bytes'));
        $this->assertSame(1, $this->bucket('free', 'file_count'));
    }

    public function test_force_delete_decrements_counters(): void
    {
        $user = $this->userWithRole('User');
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'name' => 'a.txt',
            'disk' => 'local',
            'path' => '/a',
            'size' => 500,
            'mime' => 'text/plain',
        ]);

        $file->forceDelete();

        $this->assertSame(0, $this->bucket('free', 'storage_used_bytes'));
        $this->assertSame(0, $this->bucket('free', 'file_count'));
    }

    private function bucket(string $tier, string $metricKey): int
    {
        $row = ProductAnalytic::query()
            ->where('product_key', 'FileHosting')
            ->where('tier', $tier)
            ->where('metric_key', $metricKey)
            ->first();

        return $row !== null ? (int) $row->metric_value : 0;
    }

    private function userWithRole(string $role, string $email = 'user@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $email,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
