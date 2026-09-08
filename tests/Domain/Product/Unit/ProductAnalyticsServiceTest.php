<?php

namespace Tests\Domain\Product\Unit;

use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Support\Product\Models\ProductAnalytic;
use App\Support\Product\Services\ProductActivationService;
use App\Support\Product\Services\ProductAnalyticsService;
use Database\Seeders\ArchivdRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ProductAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductAnalyticsService $analytics;
    private ProductActivationService $activations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);

        config([
            'archivd.disk' => 'local',
            'archivd.default_quota_bytes' => 1024 * 1024,
            'archivd.allowed_mime_prefixes' => ['image/', 'application/', 'text/'],
        ]);

        Storage::fake('local');
        Cache::flush();

        $this->analytics = app(ProductAnalyticsService::class);
        $this->activations = app(ProductActivationService::class);
    }

    public function test_increment_creates_bucket_row_with_delta(): void
    {
        $user = $this->userWithRole('User');

        $this->analytics->increment('Archivd', $user->id, 'storage_used_bytes', 500);

        $row = $this->bucketRow('Archivd', 'free', 'storage_used_bytes');

        $this->assertNotNull($row);
        $this->assertSame(500, $row->metric_value);
    }

    public function test_increment_resolves_tier_from_user_state(): void
    {
        // A user with no entitlement lands in the 'free' bucket.
        $user = $this->userWithRole('User');

        $this->analytics->increment('Archivd', $user->id, 'file_count', 1);

        $this->assertNotNull($this->bucketRow('Archivd', 'free', 'file_count'));
        $this->assertNull($this->bucketRow('Archivd', 'paid', 'file_count'));
    }

    public function test_increment_accumulates_on_existing_bucket(): void
    {
        $user = $this->userWithRole('User');

        $this->analytics->increment('Archivd', $user->id, 'file_count', 1);
        $this->analytics->increment('Archivd', $user->id, 'file_count', 1);
        $this->analytics->increment('Archivd', $user->id, 'file_count', 3);

        $row = $this->bucketRow('Archivd', 'free', 'file_count');

        $this->assertSame(5, $row->metric_value);
    }

    public function test_decrement_reduces_value_floored_at_zero(): void
    {
        $user = $this->userWithRole('User');

        $this->analytics->increment('Archivd', $user->id, 'file_count', 2);
        $this->analytics->decrement('Archivd', $user->id, 'file_count', 5);

        $row = $this->bucketRow('Archivd', 'free', 'file_count');

        $this->assertSame(0, $row->metric_value);
    }

    public function test_move_user_tier_transfers_contribution_between_buckets(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Archivd');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'name' => 'a.txt',
            'disk' => 'local',
            'path' => '/a',
            'size' => 500,
            'mime' => 'text/plain',
        ]);

        // free bucket now holds 500 bytes + 1 file from the HostedFile hook.
        $this->assertSame(500, $this->bucketRow('Archivd', 'free', 'storage_used_bytes')->metric_value);
        $this->assertSame(1, $this->bucketRow('Archivd', 'free', 'file_count')->metric_value);

        $this->analytics->moveUserTier('Archivd', $user->id, 'free', 'paid');

        $this->assertSame(0, $this->bucketRow('Archivd', 'free', 'storage_used_bytes')->metric_value);
        $this->assertSame(0, $this->bucketRow('Archivd', 'free', 'file_count')->metric_value);
        $this->assertSame(500, $this->bucketRow('Archivd', 'paid', 'storage_used_bytes')->metric_value);
        $this->assertSame(1, $this->bucketRow('Archivd', 'paid', 'file_count')->metric_value);
    }

    public function test_move_user_tier_noop_when_same_tier(): void
    {
        $user = $this->userWithRole('User');

        $this->analytics->increment('Archivd', $user->id, 'file_count', 3);
        $this->analytics->moveUserTier('Archivd', $user->id, 'free', 'free');

        $this->assertSame(3, $this->bucketRow('Archivd', 'free', 'file_count')->metric_value);
    }

    public function test_flush_cache_clears_metrics_cache(): void
    {
        Cache::put('product_insights.Archivd.metrics', ['stale'], 60);

        $this->analytics->flushCache('Archivd');

        $this->assertFalse(Cache::has('product_insights.Archivd.metrics'));
    }

    public function test_increment_flushes_cache(): void
    {
        Cache::put('product_insights.Archivd.metrics', ['stale'], 60);
        $user = $this->userWithRole('User');

        $this->analytics->increment('Archivd', $user->id, 'file_count', 1);

        $this->assertFalse(Cache::has('product_insights.Archivd.metrics'));
    }

    public function test_recompute_archivd_tier_matches_live_counts(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Archivd');

        HostedFile::query()->insert([
            ['id' => (string) \Illuminate\Support\Str::uuid7(), 'user_id' => $user->id, 'name' => 'a.txt', 'disk' => 'local', 'path' => '/a', 'size' => 150, 'mime' => 'text/plain', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) \Illuminate\Support\Str::uuid7(), 'user_id' => $user->id, 'name' => 'b.txt', 'disk' => 'local', 'path' => '/b', 'size' => 250, 'mime' => 'text/plain', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Recompute the free tier — the user has no entitlement.
        $this->analytics->recomputeArchivdTier('free');

        $this->assertSame(400, $this->bucketRow('Archivd', 'free', 'storage_used_bytes')->metric_value);
        $this->assertSame(2, $this->bucketRow('Archivd', 'free', 'file_count')->metric_value);
        $this->assertSame(0, $this->bucketRow('Archivd', 'free', 'workspace_count_owned')->metric_value);
    }

    public function test_recompute_product_clears_stale_buckets(): void
    {
        // Seed a stale paid bucket with no contributing users.
        ProductAnalytic::create([
            'product_key' => 'Archivd',
            'tier' => 'paid',
            'metric_key' => 'storage_used_bytes',
            'metric_value' => 999999,
        ]);

        $this->analytics->recomputeProduct('Archivd');

        $this->assertSame(0, $this->bucketRow('Archivd', 'paid', 'storage_used_bytes')->metric_value);
    }

    private function bucketRow(string $productKey, string $tier, string $metricKey): ?ProductAnalytic
    {
        return ProductAnalytic::query()
            ->where('product_key', $productKey)
            ->where('tier', $tier)
            ->where('metric_key', $metricKey)
            ->first();
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
