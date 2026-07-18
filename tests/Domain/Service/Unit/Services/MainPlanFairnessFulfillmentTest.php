<?php

namespace Tests\Domain\Service\Unit\Services;

use App\Domain\FileHosting\Services\QuotaService;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceFulfillmentService;
use App\Domain\Service\Support\FileHostingCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MainPlanFairnessFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['file-hosting.default_quota_bytes' => FileHostingCatalog::freeBytes()]);
    }

    public function test_downgrade_zero_charge_sets_period_with_surplus_days(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $main2 = Service::factory()->fileHostingMain2Tb()->create();
        $main1 = Service::factory()->fileHostingMain1Tb()->create();

        ServiceEntitlement::query()->create([
            'user_id' => $user->id,
            'service_id' => $main2->id,
            'status' => EntitlementStatus::Active,
            'quantity' => 1,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(29),
        ]);

        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $main1->id,
            'change_type' => PurchaseChangeType::Downgrade,
            'list_amount' => 15,
            'credit_amount' => 29,
            'charge_amount' => 0,
            'display_amount' => 0,
            'settlement_amount' => 0,
            'surplus_days' => 28,
        ]);

        app(ServiceFulfillmentService::class)->fulfill($purchase);

        $purchase->refresh();
        $this->assertSame(PurchaseStatus::Paid, $purchase->status);

        $newMain = ServiceEntitlement::query()
            ->where('user_id', $user->id)
            ->where('service_id', $main1->id)
            ->first();

        $this->assertNotNull($newMain);
        $this->assertSame(EntitlementStatus::Active, $newMain->status);
        // now + 30 days + 28 surplus days
        $this->assertTrue(
            $newMain->ends_at->equalTo(Carbon::parse('2026-09-12 12:00:00'))
        );

        $this->assertDatabaseHas('service_entitlements', [
            'user_id' => $user->id,
            'service_id' => $main2->id,
            'status' => EntitlementStatus::Expired->value,
        ]);

        $expectedQuota = FileHostingCatalog::freeBytes() + FileHostingCatalog::main1TbBytes();
        $this->assertSame($expectedQuota, app(QuotaService::class)->quotaBytesFor($user->id));

        Carbon::setTestNow();
    }

    public function test_renew_stacks_from_remaining_ends_at(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $main = Service::factory()->fileHostingMain1Tb()->create();

        ServiceEntitlement::query()->create([
            'user_id' => $user->id,
            'service_id' => $main->id,
            'status' => EntitlementStatus::Active,
            'quantity' => 1,
            'starts_at' => now()->subDays(10),
            'ends_at' => Carbon::parse('2026-08-01 12:00:00'),
        ]);

        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $main->id,
            'change_type' => PurchaseChangeType::Renew,
            'list_amount' => 24.95,
            'charge_amount' => 24.95,
            'display_amount' => 24.95,
            'surplus_days' => 0,
        ]);

        app(ServiceFulfillmentService::class)->fulfill($purchase);

        $ent = ServiceEntitlement::query()
            ->where('user_id', $user->id)
            ->where('service_id', $main->id)
            ->first();

        // remaining end Aug 1 + 30 days
        $this->assertTrue(
            $ent->ends_at->equalTo(Carbon::parse('2026-08-31 12:00:00'))
        );

        Carbon::setTestNow();
    }
}
