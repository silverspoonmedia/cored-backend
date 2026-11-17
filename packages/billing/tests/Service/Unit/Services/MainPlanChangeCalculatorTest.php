<?php

namespace App\Domain\Service\Tests\Unit\Services;

use App\Domain\Service\Tests\Support\TestPlans;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\MainPlanChangeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MainPlanChangeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private MainPlanChangeCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = app(MainPlanChangeCalculator::class);
    }

    public function test_new_main_full_price(): void
    {
        $user = User::factory()->create();
        $main = Service::factory()->plan(TestPlans::main1Tb())->create();

        $quote = $this->calc->quote($main, $user->id, 24.95);

        $this->assertSame(PurchaseChangeType::New, $quote->changeType);
        $this->assertSame(24.95, $quote->listAmount);
        $this->assertSame(0.0, $quote->creditAmount);
        $this->assertSame(24.95, $quote->chargeAmount);
        $this->assertSame(0, $quote->surplusDays);
    }

    public function test_renew_same_main_full_price(): void
    {
        $user = User::factory()->create();
        $main = Service::factory()->plan(TestPlans::main1Tb())->create();
        $this->seedActiveMain($user, $main, 24.95, now()->addDays(10));

        $quote = $this->calc->quote($main, $user->id, 24.95);

        $this->assertSame(PurchaseChangeType::Renew, $quote->changeType);
        $this->assertSame(24.95, $quote->chargeAmount);
        $this->assertSame(0.0, $quote->creditAmount);
    }

    public function test_downgrade_day_15_zero_charge_exact_cover(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $main30 = Service::factory()->plan(TestPlans::main2Tb())->create();
        $main15 = Service::factory()->plan(TestPlans::main1Tb())->create();

        // Paid $30; 15 days remaining of 30 → credit $15; target $15 → charge 0, surplus 0
        // (credit fully pays one period; surplus_days only when credit > list)
        $this->seedActiveMain($user, $main30, 30.00, now()->addDays(15));

        $quote = $this->calc->quote($main15, $user->id, 15.00);

        $this->assertSame(PurchaseChangeType::Downgrade, $quote->changeType);
        $this->assertEqualsWithDelta(15.0, $quote->creditAmount, 0.05);
        $this->assertSame(0.0, $quote->chargeAmount);
        $this->assertSame(0, $quote->surplusDays);

        Carbon::setTestNow();
    }

    public function test_downgrade_early_converts_surplus_to_extra_days(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $main30 = Service::factory()->plan(TestPlans::main2Tb())->create();
        $main15 = Service::factory()->plan(TestPlans::main1Tb())->create();

        // Paid $30; 29 days remaining → credit ~$29; target $15 → charge 0, surplus ~$14 → ~28 days
        $this->seedActiveMain($user, $main30, 30.00, now()->addDays(29));

        $quote = $this->calc->quote($main15, $user->id, 15.00);

        $this->assertSame(PurchaseChangeType::Downgrade, $quote->changeType);
        $this->assertSame(0.0, $quote->chargeAmount);
        $this->assertGreaterThan(20, $quote->surplusDays);

        Carbon::setTestNow();
    }

    public function test_downgrade_day_25_partial_charge(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $main30 = Service::factory()->plan(TestPlans::main2Tb())->create();
        $main15 = Service::factory()->plan(TestPlans::main1Tb())->create();

        // 5 days remaining of 30 on $30 → credit $5; target $15 → charge $10
        $this->seedActiveMain($user, $main30, 30.00, now()->addDays(5));

        $quote = $this->calc->quote($main15, $user->id, 15.00);

        $this->assertSame(PurchaseChangeType::Downgrade, $quote->changeType);
        $this->assertEqualsWithDelta(5.0, $quote->creditAmount, 0.05);
        $this->assertEqualsWithDelta(10.0, $quote->chargeAmount, 0.05);
        $this->assertSame(0, $quote->surplusDays);

        Carbon::setTestNow();
    }

    public function test_upgrade_surcharge(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $main1 = Service::factory()->plan(TestPlans::main1Tb())->create();
        $main2 = Service::factory()->plan(TestPlans::main2Tb())->create();

        // 15 days left on $15 → credit $7.5; target $30 → charge $22.5
        $this->seedActiveMain($user, $main1, 15.00, now()->addDays(15));

        $quote = $this->calc->quote($main2, $user->id, 30.00);

        $this->assertSame(PurchaseChangeType::Upgrade, $quote->changeType);
        $this->assertEqualsWithDelta(7.5, $quote->creditAmount, 0.05);
        $this->assertEqualsWithDelta(22.5, $quote->chargeAmount, 0.05);
        $this->assertSame(0, $quote->surplusDays);

        Carbon::setTestNow();
    }

    public function test_addon_is_unit_full_price(): void
    {
        $user = User::factory()->create();
        $addon = Service::factory()->plan(TestPlans::addon100Gb())->create();

        $quote = $this->calc->quote($addon, $user->id, 2.95);

        $this->assertSame(PurchaseChangeType::AddonUnit, $quote->changeType);
        $this->assertSame(2.95, $quote->chargeAmount);
    }

    private function seedActiveMain(User $user, Service $service, float $paidAmount, Carbon $endsAt): void
    {
        $purchase = ServicePurchase::factory()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'status' => PurchaseStatus::Paid,
            'display_amount' => $paidAmount,
            'list_amount' => $paidAmount,
            'charge_amount' => $paidAmount,
        ]);

        ServiceEntitlement::query()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'status' => EntitlementStatus::Active,
            'quantity' => 1,
            'starts_at' => $endsAt->copy()->subDays(30),
            'ends_at' => $endsAt,
            'source_purchase_id' => $purchase->id,
        ]);
    }
}
