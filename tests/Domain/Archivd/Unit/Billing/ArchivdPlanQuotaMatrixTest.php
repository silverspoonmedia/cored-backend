<?php

namespace Tests\Domain\Archivd\Unit\Billing;

use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Archivd\Support\ArchivdCatalog;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Success matrix: free always stacks; main exclusive; addons quantity-stack.
 */
class ArchivdPlanQuotaMatrixTest extends TestCase
{
    use RefreshDatabase;

    private int $free;

    protected function setUp(): void
    {
        parent::setUp();
        $this->free = ArchivdCatalog::freeBytes();
        config(['archivd.default_quota_bytes' => $this->free]);
    }

    public function test_main_1tb_plus_free(): void
    {
        $user = User::factory()->create();
        $main = Service::factory()->archivdMain1Tb()->create();
        $this->fulfill($user, $main);

        $expected = $this->free + ArchivdCatalog::main1TbBytes();
        $quota = app(QuotaService::class)->usage($user->id);
        $this->assertSame($expected, $quota['quota_bytes']);
        $this->assertSame('paid', $quota['state']);
        $this->assertSame(1, ServiceEntitlement::query()->where('user_id', $user->id)->where('status', EntitlementStatus::Active)->count());
    }

    public function test_main_2tb_replaces_main_1tb(): void
    {
        $user = User::factory()->create();
        $main1 = Service::factory()->archivdMain1Tb()->create();
        $main2 = Service::factory()->archivdMain2Tb()->create();

        $this->fulfill($user, $main1);
        $this->fulfill($user, $main2);

        $this->assertDatabaseHas('service_entitlements', [
            'user_id' => $user->id,
            'service_id' => $main1->id,
            'status' => EntitlementStatus::Expired->value,
        ]);
        $this->assertDatabaseHas('service_entitlements', [
            'user_id' => $user->id,
            'service_id' => $main2->id,
            'status' => EntitlementStatus::Active->value,
            'quantity' => 1,
        ]);

        $expected = $this->free + ArchivdCatalog::main2TbBytes();
        $this->assertSame($expected, app(QuotaService::class)->quotaBytesFor($user->id));
    }

    public function test_addon_100gb_quantity_three(): void
    {
        $user = User::factory()->create();
        $addon = Service::factory()->archivdAddon100Gb()->create();

        $this->fulfill($user, $addon);
        $this->fulfill($user, $addon);
        $this->fulfill($user, $addon);

        $this->assertDatabaseHas('service_entitlements', [
            'user_id' => $user->id,
            'service_id' => $addon->id,
            'quantity' => 3,
            'status' => EntitlementStatus::Active->value,
        ]);

        $expected = $this->free + (3 * ArchivdCatalog::addon100GbBytes());
        $this->assertSame($expected, app(QuotaService::class)->quotaBytesFor($user->id));
    }

    public function test_addon_100gb_single_purchase_quantity_three_matches_three_checkouts(): void
    {
        $user = User::factory()->create();
        $addon = Service::factory()->archivdAddon100Gb()->create();

        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $addon->id,
            'quantity' => 3,
        ]);
        app(ServiceFulfillmentService::class)->fulfill($purchase);

        $this->assertDatabaseHas('service_entitlements', [
            'user_id' => $user->id,
            'service_id' => $addon->id,
            'quantity' => 3,
            'status' => EntitlementStatus::Active->value,
        ]);

        $expected = $this->free + (3 * ArchivdCatalog::addon100GbBytes());
        $this->assertSame($expected, app(QuotaService::class)->quotaBytesFor($user->id));
    }

    public function test_two_addon_500gb_without_main(): void
    {
        $user = User::factory()->create();
        $addon = Service::factory()->archivdAddon500Gb()->create();

        $this->fulfill($user, $addon);
        $this->fulfill($user, $addon);

        // 2×500 GiB + free 5 GiB (product copy may say "~1 TB"; math is binary GiB).
        $expected = $this->free + (2 * ArchivdCatalog::addon500GbBytes());
        $this->assertSame($expected, app(QuotaService::class)->quotaBytesFor($user->id));
    }

    public function test_combined_main_and_addons_matrix(): void
    {
        $user = User::factory()->create();
        $main = Service::factory()->archivdMain1Tb()->create();
        $addon100 = Service::factory()->archivdAddon100Gb()->create();
        $addon500 = Service::factory()->archivdAddon500Gb()->create();

        $this->fulfill($user, $main);
        $this->fulfill($user, $addon100);
        $this->fulfill($user, $addon100);
        $this->fulfill($user, $addon100);
        $this->fulfill($user, $addon500);
        $this->fulfill($user, $addon500);

        // 1 TB + 3×100 GB + 2×500 GB + free 5 GB = 2.3 TB + 5 GB
        $expected = $this->free
            + ArchivdCatalog::main1TbBytes()
            + (3 * ArchivdCatalog::addon100GbBytes())
            + (2 * ArchivdCatalog::addon500GbBytes());

        $this->assertSame($expected, app(QuotaService::class)->quotaBytesFor($user->id));

        $this->assertDatabaseHas('service_entitlements', [
            'service_id' => $addon100->id,
            'quantity' => 3,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('service_entitlements', [
            'service_id' => $addon500->id,
            'quantity' => 2,
            'status' => 'active',
        ]);
    }

    private function fulfill(User $user, Service $service): void
    {
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
        ]);
        app(ServiceFulfillmentService::class)->fulfill($purchase);
    }
}
