<?php

namespace Tests\Domain\Product\Unit;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\Identity\Models\User;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Exceptions\ProductNotFoundException;
use App\Support\Product\Services\ProductActivationService;
use App\Support\Product\Services\ProductAnalyticsService;
use App\Support\Product\Services\ProductInsightService;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ProductInsightServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductInsightService $insights;
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

        $this->activations = app(ProductActivationService::class);
        $this->insights = app(ProductInsightService::class);
    }

    public function test_product_summary_shape_for_file_hosting(): void
    {
        $summary = $this->insights->productSummary('FileHosting');

        $this->assertSame('FileHosting', $summary['product_key']);
        $this->assertSame('implicit', $summary['activation_type']);
        $this->assertSame('file_hosting', $summary['fulfillment_target']);
        $this->assertArrayHasKey('active', $summary['counts']);
        $this->assertArrayHasKey('suspended', $summary['counts']);
        $this->assertArrayHasKey('pending', $summary['counts']);
        $this->assertArrayHasKey('expired', $summary['counts']);
        $this->assertArrayHasKey('total', $summary['counts']);
        $this->assertArrayHasKey('product_metrics', $summary);
    }

    public function test_product_summary_counts_reflect_activations(): void
    {
        $active = $this->userWithRole('User', 'active@example.com');
        $suspended = $this->userWithRole('User', 'suspended@example.com');
        $expired = $this->userWithRole('User', 'expired@example.com');

        $a = $this->activations->activateImplicit($active, 'FileHosting');
        $b = $this->activations->activateImplicit($suspended, 'FileHosting');
        $c = $this->activations->activateImplicit($expired, 'FileHosting');
        $this->activations->suspend($b);
        $this->activations->markExpired($c);

        $counts = $this->insights->productSummary('FileHosting')['counts'];

        $this->assertSame(1, $counts['active']);
        $this->assertSame(1, $counts['suspended']);
        $this->assertSame(1, $counts['expired']);
        $this->assertSame(0, $counts['pending']);
        $this->assertSame(3, $counts['total']);
    }

    public function test_product_metrics_for_file_hosting_returns_expected_keys(): void
    {
        $metrics = $this->insights->productMetrics('FileHosting');

        $this->assertArrayHasKey('total_storage_used_bytes', $metrics);
        $this->assertArrayHasKey('total_files', $metrics);
        $this->assertArrayHasKey('total_workspaces', $metrics);
        $this->assertArrayHasKey('paid_users_count', $metrics);
        $this->assertArrayHasKey('free_users_count', $metrics);
        $this->assertArrayHasKey('grace_users_count', $metrics);
        $this->assertArrayHasKey('by_tier', $metrics);
    }

    public function test_product_metrics_for_unknown_product_returns_empty(): void
    {
        config()->set('products.Unknown', ['activation_type' => 'implicit', 'fulfillment_target' => null]);

        $this->assertSame([], $this->insights->productMetrics('Unknown'));
    }

    public function test_file_hosting_metrics_count_files_and_workspaces(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'FileHosting');

        // Use Eloquent create to trigger the analytics hooks (lands in free bucket).
        HostedFile::query()->create(['user_id' => $user->id, 'name' => 'a.txt', 'disk' => 'local', 'path' => '/a', 'size' => 100, 'mime' => 'text/plain']);
        HostedFile::query()->create(['user_id' => $user->id, 'name' => 'b.txt', 'disk' => 'local', 'path' => '/b', 'size' => 100, 'mime' => 'text/plain']);
        HostedFile::query()->create(['user_id' => $user->id, 'name' => 'c.txt', 'disk' => 'local', 'path' => '/c', 'size' => 100, 'mime' => 'text/plain']);
        // Workspace has no model-level hook; insert directly then recompute the tier.
        Workspace::query()->insert([
            ['id' => (string) \Illuminate\Support\Str::uuid7(), 'owner_user_id' => $user->id, 'name' => 'ws1', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) \Illuminate\Support\Str::uuid7(), 'owner_user_id' => $user->id, 'name' => 'ws2', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        app(ProductAnalyticsService::class)->recomputeFileHostingTier('free');

        $metrics = $this->insights->productMetrics('FileHosting');

        $this->assertSame(3, $metrics['total_files']);
        $this->assertSame(2, $metrics['total_workspaces']);
        $this->assertSame(300, $metrics['total_storage_used_bytes']);
    }

    public function test_user_detail_in_product_returns_activation_and_quota(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'FileHosting');

        HostedFile::query()->create(['user_id' => $user->id, 'name' => 'a.txt', 'disk' => 'local', 'path' => '/a', 'size' => 50, 'mime' => 'text/plain']);
        HostedFile::query()->create(['user_id' => $user->id, 'name' => 'b.txt', 'disk' => 'local', 'path' => '/b', 'size' => 50, 'mime' => 'text/plain']);
        Workspace::query()->insert([
            ['id' => (string) \Illuminate\Support\Str::uuid7(), 'owner_user_id' => $user->id, 'name' => 'ws1', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Drill-down reads source tables directly — no analytics recompute needed.
        $detail = $this->insights->userDetailInProduct($user->id, 'FileHosting');

        $this->assertSame($user->id, $detail['user_id']);
        $this->assertSame('FileHosting', $detail['product_key']);
        $this->assertNotNull($detail['activation']);
        $this->assertSame(ProductActivationState::Active, $detail['activation']['state']);
        $this->assertSame(2, $detail['file_count']);
        $this->assertSame(1, $detail['workspace_count_owned']);
        $this->assertArrayHasKey('quota', $detail);
        $this->assertSame('free', $detail['tier']);
    }

    public function test_user_detail_for_user_without_activation(): void
    {
        $user = $this->userWithRole('User');

        $detail = $this->insights->userDetailInProduct($user->id, 'FileHosting');

        $this->assertNull($detail['activation']);
        $this->assertSame(0, $detail['file_count']);
    }

    public function test_product_summary_throws_for_unknown_product(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->insights->productSummary('Nonexistent');
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
