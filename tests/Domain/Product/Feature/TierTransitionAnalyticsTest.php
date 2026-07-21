<?php

namespace Tests\Domain\Product\Feature;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceEntitlementService;
use App\Domain\Service\Services\ServiceFulfillmentService;
use App\Support\Product\Models\ProductAnalytic;
use App\Support\Product\Services\ProductActivationService;
use Database\Factories\ServiceFactory;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class TierTransitionAnalyticsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ServiceEntitlementService $entitlements;
    private ServiceFulfillmentService $fulfillment;
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
            'service.grace_days' => 7,
        ]);

        Storage::fake('local');

        $this->entitlements = app(ServiceEntitlementService::class);
        $this->fulfillment = app(ServiceFulfillmentService::class);
        $this->activations = app(ProductActivationService::class);
    }

    public function test_fulfill_paid_main_moves_contribution_to_paid_bucket(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'FileHosting');
        HostedFile::query()->create([
            'user_id' => $user->id,
            'name' => 'a.txt',
            'disk' => 'local',
            'path' => '/a',
            'size' => 100,
            'mime' => 'text/plain',
        ]);

        // Upload lands in the free bucket (user has no entitlement yet).
        $this->assertSame(100, $this->bucket('free', 'storage_used_bytes'));
        $this->assertSame(1, $this->bucket('free', 'file_count'));
        $this->assertNull($this->bucketRow('paid', 'storage_used_bytes'));

        $service = ServiceFactory::new()->fileHostingMain1Tb()->create();
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
        ]);

        $this->fulfillment->fulfill($purchase);

        // moveUserTier(free -> paid) transferred the contribution.
        $this->assertSame(0, $this->bucket('free', 'storage_used_bytes'));
        $this->assertSame(0, $this->bucket('free', 'file_count'));
        $this->assertSame(100, $this->bucket('paid', 'storage_used_bytes'));
        $this->assertSame(1, $this->bucket('paid', 'file_count'));
    }

    public function test_enter_grace_moves_contribution_to_grace_bucket(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'FileHosting');
        HostedFile::query()->create([
            'user_id' => $user->id,
            'name' => 'a.txt',
            'disk' => 'local',
            'path' => '/a',
            'size' => 100,
            'mime' => 'text/plain',
        ]);

        $service = ServiceFactory::new()->fileHostingMain1Tb()->create();
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
        ]);
        $this->fulfillment->fulfill($purchase);

        $entitlement = ServiceEntitlement::query()
            ->where('user_id', $user->id)
            ->where('service_id', $service->id)
            ->first();
        $this->entitlements->enterGrace($entitlement);

        // paid -> grace transfer.
        $this->assertSame(0, $this->bucket('paid', 'storage_used_bytes'));
        $this->assertSame(100, $this->bucket('grace', 'storage_used_bytes'));
        $this->assertSame(1, $this->bucket('grace', 'file_count'));
    }

    public function test_mark_expired_when_no_other_paid_moves_contribution_to_free_bucket(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'FileHosting');
        HostedFile::query()->create([
            'user_id' => $user->id,
            'name' => 'a.txt',
            'disk' => 'local',
            'path' => '/a',
            'size' => 100,
            'mime' => 'text/plain',
        ]);

        $service = ServiceFactory::new()->fileHostingMain1Tb()->create();
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
        ]);
        $this->fulfillment->fulfill($purchase);

        $entitlement = ServiceEntitlement::query()
            ->where('user_id', $user->id)
            ->where('service_id', $service->id)
            ->first();
        $this->entitlements->markExpired($entitlement);

        // paid -> free transfer (no other paid entitlement remains).
        $this->assertSame(0, $this->bucket('paid', 'storage_used_bytes'));
        $this->assertSame(100, $this->bucket('free', 'storage_used_bytes'));
        $this->assertSame(1, $this->bucket('free', 'file_count'));
    }

    private function bucket(string $tier, string $metricKey): int
    {
        $row = $this->bucketRow($tier, $metricKey);

        return $row !== null ? (int) $row->metric_value : 0;
    }

    private function bucketRow(string $tier, string $metricKey): ?ProductAnalytic
    {
        return ProductAnalytic::query()
            ->where('product_key', 'FileHosting')
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
