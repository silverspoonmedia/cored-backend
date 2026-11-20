<?php

namespace App\Domain\Service\Tests\Unit\Services;

use App\Domain\Currency\Tests\Concerns\SeedsCurrencies;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceCheckoutService;
use App\Domain\Service\Tests\Support\TestPlans;
use App\Domain\Service\Tests\TestCase;
use Illuminate\Support\Carbon;

class ServiceCheckoutZeroChargeTest extends TestCase
{
    use SeedsCurrencies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCurrencies();
    }

    public function test_zero_charge_downgrade_fulfills_immediately_without_pending_invoice(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');

        $user = User::factory()->create();
        $main2 = Service::factory()->plan(TestPlans::main2Tb())->create();
        $main1 = Service::factory()->plan(TestPlans::main1Tb())->create();
        ServicePrice::factory()->usd(15)->create(['service_id' => $main1->id]);

        $paid = ServicePurchase::factory()->create([
            'user_id' => $user->id,
            'service_id' => $main2->id,
            'status' => PurchaseStatus::Paid,
            'display_amount' => 30,
            'list_amount' => 30,
            'charge_amount' => 30,
        ]);

        ServiceEntitlement::query()->create([
            'user_id' => $user->id,
            'service_id' => $main2->id,
            'status' => EntitlementStatus::Active,
            'quantity' => 1,
            'starts_at' => now()->subDays(15),
            'ends_at' => now()->addDays(15),
            'source_purchase_id' => $paid->id,
        ]);

        $result = app(ServiceCheckoutService::class)->checkout($main1, $user, 'USD');

        $this->assertSame(0.0, $result['quote']['charge_amount']);
        $this->assertSame('downgrade', $result['quote']['change_type']);
        $this->assertSame(PurchaseStatus::Paid, $result['purchase']->status);
        $this->assertNull($result['purchase']->invoice);

        Carbon::setTestNow();
    }
}
