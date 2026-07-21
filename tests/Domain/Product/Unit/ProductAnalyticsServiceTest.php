<?php

namespace Tests\Domain\Product\Unit;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Support\Product\Models\ProductAnalytic;
use App\Support\Product\Services\ProductActivationService;
use App\Support\Product\Services\ProductAnalyticsService;
use Database\Seeders\FileHostingRoleSeeder;
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
        $this->seed(FileHostingRoleSeeder::class);

        config([
            'file-hosting.disk' => 'local',
            'file-hosting.default_quota_bytes' => 1024 * 1024,
            'file-hosting.allowed_mime_prefixes' => ['image/', 'application/', 'text/'],
        ]);

        Storage::fake('local');
        Cache::flush();

        $this->analytics = app(ProductAnalyticsService::class);
        $this->activations = app(ProductActivationService::class);
    }

    public function test_increment_creates_bucket_row_with_delta(): void
    {
        $user = $this->userWithRole('User');

        $this->analytics->increment('FileHosting', $user->id, 'storage_used_bytes', 500);

        $row = $this->bucketRow('FileHosting', 'free', 'storage_used_bytes');

        $this->assertNotNull($row);
        $this->assertSame(500, $row->metric_value);
    }

    public function test_increment_resolves_tier_from_user_state(): void
    {
        // A user with no entitlement lands in the 'free' bucket.
        $user = $this->userWithRole('User');

        $this->analytics->increment('FileHosting', $user->id, 'file_count', 1);

        $this->assertNotNull($this->bucketRow('FileHosting', 'free', 'file_count'));
        $this->assertNull($this->bucketRow('FileHosting', 'paid', 'file_count'));
    }

    public function test_increment_accumulates_on_existing_bucket(): void
    {
        $user = $this->userWithRole('User');

        $this->analytics->increment('FileHosting', $user->id, 'file_count', 1);
        $this->analytics->increment('FileHosting', $user->id, 'file_count', 1);
        $this->analytics->increment('FileHosting', $user->id, 'file_count', 3);

        $row = $this->bucketRow('FileHosting', 'free', 'file_count');

        $this->assertSame(5, $row->metric_value);
    }

    public function test_decrement_reduces_value_floored_at_zero(): void
    {
        $user = $this->userWithRole('User');

        $this->analytics->increment('FileHosting', $user->id, 'file_count', 2);
        $this->analytics->decrement('FileHosting', $user->id, 'file_count', 5);

        $row = $this->bucketRow('FileHosting', 'free', 'file_count');

        $this->assertSame(0, $row->metric_value);
    }

    public function test_move_user_tier_transfers_contribution_between_buckets(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'FileHosting');

        HostedFile::query()->create([
            'user_id' => $user->id,
            'name' => 'a.txt',
            'disk' => 'local',
            'path' => '/a',
            'size' => 500,
            'mime' => 'text/plain',
        ]);

        // free bucket now holds 500 bytes + 1 file from the HostedFile hook.
        $this->assertSame(500, $this->bucketRow('FileHosting', 'free', 'storage_used_bytes')->metric_value);
        $this->assertSame(1, $this->bucketRow('FileHosting', 'free', 'file_count')->metric_value);

        $this->analytics->moveUserTier('FileHosting', $user->id, 'free', 'paid');

        $this->assertSame(0, $this->bucketRow('FileHosting', 'free', 'storage_used_bytes')->metric_value);
        $this->assertSame(0, $this->bucketRow('FileHosting', 'free', 'file_count')->metric_value);
        $this->assertSame(500, $this->bucketRow('FileHosting', 'paid', 'storage_used_bytes')->metric_value);
        $this->assertSame(1, $this->bucketRow('FileHosting', 'paid', 'file_count')->metric_value);
    }

    public function test_move_user_tier_noop_when_same_tier(): void
    {
        $user = $this->userWithRole('User');

        $this->analytics->increment('FileHosting', $user->id, 'file_count', 3);
        $this->analytics->moveUserTier('FileHosting', $user->id, 'free', 'free');

        $this->assertSame(3, $this->bucketRow('FileHosting', 'free', 'file_count')->metric_value);
    }

    public function test_flush_cache_clears_metrics_cache(): void
    {
        Cache::put('product_insights.FileHosting.metrics', ['stale'], 60);

        $this->analytics->flushCache('FileHosting');

        $this->assertFalse(Cache::has('product_insights.FileHosting.metrics'));
    }

    public function test_increment_flushes_cache(): void
    {
        Cache::put('product_insights.FileHosting.metrics', ['stale'], 60);
        $user = $this->userWithRole('User');

        $this->analytics->increment('FileHosting', $user->id, 'file_count', 1);

        $this->assertFalse(Cache::has('product_insights.FileHosting.metrics'));
    }

    public function test_recompute_file_hosting_tier_matches_live_counts(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'FileHosting');

        HostedFile::query()->insert([
            ['id' => (string) \Illuminate\Support\Str::uuid7(), 'user_id' => $user->id, 'name' => 'a.txt', 'disk' => 'local', 'path' => '/a', 'size' => 150, 'mime' => 'text/plain', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) \Illuminate\Support\Str::uuid7(), 'user_id' => $user->id, 'name' => 'b.txt', 'disk' => 'local', 'path' => '/b', 'size' => 250, 'mime' => 'text/plain', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Recompute the free tier — the user has no entitlement.
        $this->analytics->recomputeFileHostingTier('free');

        $this->assertSame(400, $this->bucketRow('FileHosting', 'free', 'storage_used_bytes')->metric_value);
        $this->assertSame(2, $this->bucketRow('FileHosting', 'free', 'file_count')->metric_value);
        $this->assertSame(0, $this->bucketRow('FileHosting', 'free', 'workspace_count_owned')->metric_value);
    }

    public function test_recompute_product_clears_stale_buckets(): void
    {
        // Seed a stale paid bucket with no contributing users.
        ProductAnalytic::create([
            'product_key' => 'FileHosting',
            'tier' => 'paid',
            'metric_key' => 'storage_used_bytes',
            'metric_value' => 999999,
        ]);

        $this->analytics->recomputeProduct('FileHosting');

        $this->assertSame(0, $this->bucketRow('FileHosting', 'paid', 'storage_used_bytes')->metric_value);
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
