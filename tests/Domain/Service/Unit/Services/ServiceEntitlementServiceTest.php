<?php

namespace Tests\Domain\Service\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceEntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceEntitlementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ServiceEntitlementService::class);
    }

    public function test_once_creates_permanent_entitlement(): void
    {
        $user = User::factory()->create();
        $catalog = Service::factory()->create([
            'billing_interval' => BillingInterval::Once,
            'fulfillment_target' => 'file_hosting',
            'entitlement' => 'unlimited_quota',
        ]);
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $catalog->id,
        ]);

        $result = $this->service->activateFromPurchase($purchase);

        $this->assertSame(EntitlementStatus::Active, $result['entitlement']->status);
        $this->assertNull($result['entitlement']->ends_at);
        $this->assertNull($result['period_start']);
        $this->assertNull($result['period_end']);
        $this->assertTrue($result['entitlement']->isPermanent());
    }

    public function test_monthly_sets_ends_at_one_month_ahead(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $catalog = Service::factory()->fileHostingMonthly()->create();
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $catalog->id,
        ]);

        $result = $this->service->activateFromPurchase($purchase);

        $this->assertSame(EntitlementStatus::Active, $result['entitlement']->status);
        $this->assertTrue(
            $result['period_end']->equalTo(Carbon::parse('2026-08-16 12:00:00'))
        );
        $this->assertTrue(
            $result['entitlement']->ends_at->equalTo(Carbon::parse('2026-08-16 12:00:00'))
        );

        Carbon::setTestNow();
    }

    public function test_renew_extends_from_remaining_ends_at(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $catalog = Service::factory()->fileHostingMonthly()->create();
        $first = ServicePurchase::factory()->create([
            'user_id' => $user->id,
            'service_id' => $catalog->id,
            'status' => PurchaseStatus::Paid,
        ]);

        $this->service->activateFromPurchase($first);

        Carbon::setTestNow('2026-07-20 12:00:00');

        $renew = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $catalog->id,
        ]);

        $result = $this->service->activateFromPurchase($renew);

        // Remaining until Aug 16; +1 month → Sep 16
        $this->assertTrue(
            $result['entitlement']->ends_at->equalTo(Carbon::parse('2026-09-16 12:00:00'))
        );

        Carbon::setTestNow();
    }

    public function test_renew_during_grace_extends_from_now(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $catalog = Service::factory()->fileHostingMonthly()->create();
        $first = ServicePurchase::factory()->create([
            'user_id' => $user->id,
            'service_id' => $catalog->id,
            'status' => PurchaseStatus::Paid,
        ]);

        $activated = $this->service->activateFromPurchase($first);
        $entitlement = $activated['entitlement'];
        $entitlement->ends_at = Carbon::parse('2026-07-10 12:00:00');
        $entitlement->status = EntitlementStatus::Grace;
        $entitlement->grace_ends_at = Carbon::parse('2026-07-17 12:00:00');
        $entitlement->save();

        $renew = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $catalog->id,
        ]);

        $result = $this->service->activateFromPurchase($renew);

        $this->assertSame(EntitlementStatus::Active, $result['entitlement']->status);
        $this->assertNull($result['entitlement']->grace_ends_at);
        $this->assertTrue(
            $result['entitlement']->ends_at->equalTo(Carbon::parse('2026-08-16 12:00:00'))
        );

        Carbon::setTestNow();
    }
}
