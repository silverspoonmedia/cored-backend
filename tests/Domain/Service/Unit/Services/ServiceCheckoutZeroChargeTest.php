<?php

namespace Tests\Domain\Service\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Exceptions\ServiceException;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceCheckoutService;
use App\Domain\Service\Services\ServiceEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SeedsCurrencies;
use Tests\TestCase;

class ServiceCheckoutZeroChargeTest extends TestCase
{
    use RefreshDatabase;
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
        $main2 = Service::factory()->archivdMain2Tb()->create();
        $main1 = Service::factory()->archivdMain1Tb()->create();
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

    public function test_microstock_free_sku_auto_fulfills_and_activates(): void
    {
        $user = User::factory()->create();
        $free = Service::factory()->microstockPlanFree()->create();
        ServicePrice::factory()->usd(0)->create(['service_id' => $free->id]);

        $result = app(ServiceCheckoutService::class)->checkout($free, $user, 'USD');

        // $0 zero-charge: no invoice, purchase is immediately Paid.
        $this->assertSame(PurchaseStatus::Paid, $result['purchase']->status);
        $this->assertNull($result['purchase']->invoice);

        // Entitlement active, one base slot, permanent (no ends_at).
        $entitlement = ServiceEntitlement::query()
            ->where('user_id', $user->id)
            ->where('service_id', $free->id)
            ->first();
        $this->assertNotNull($entitlement);
        $this->assertSame(EntitlementStatus::Active, $entitlement->status);
        $this->assertSame(1, $entitlement->quantity);
        $this->assertNull($entitlement->ends_at);

        // Microstock product activation is implicit via fulfillment_target.
        $this->assertDatabaseHas('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'Microstock',
            'state' => 'active',
        ]);
    }

    public function test_microstock_free_sku_rebuy_blocked(): void
    {
        $user = User::factory()->create();
        $free = Service::factory()->microstockPlanFree()->create();
        ServicePrice::factory()->usd(0)->create(['service_id' => $free->id]);

        app(ServiceCheckoutService::class)->checkout($free, $user, 'USD');

        try {
            app(ServiceCheckoutService::class)->checkout($free, $user, 'USD');
            $this->fail('Rebuying the free Microstock registration must be blocked');
        } catch (ServiceException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertStringContainsString(
                __('api/services.already_activated'),
                $e->getMessage(),
            );
        }
    }

    public function test_microstock_slot_addon_compounds_quantity(): void
    {
        $user = User::factory()->create();
        $addon = Service::factory()->microstockAddonWorkspaceSlot()->create();
        ServicePrice::factory()->usd(1)->create(['service_id' => $addon->id]);

        $first = ServicePurchase::factory()->create([
            'user_id' => $user->id,
            'service_id' => $addon->id,
            'status' => PurchaseStatus::Paid,
        ]);
        $second = ServicePurchase::factory()->create([
            'user_id' => $user->id,
            'service_id' => $addon->id,
            'status' => PurchaseStatus::Paid,
        ]);

        $entitlements = app(ServiceEntitlementService::class);
        $entitlements->activateFromPurchase($first);
        $entitlements->activateFromPurchase($second);

        $entitlement = ServiceEntitlement::query()
            ->where('user_id', $user->id)
            ->where('service_id', $addon->id)
            ->first();
        $this->assertNotNull($entitlement);
        $this->assertSame(2, $entitlement->quantity);
        $this->assertSame(2, $entitlements->microstockWorkspaceSlotExtraQuantity($user->id));
        $this->assertTrue($entitlements->hasActiveMicrostockWorkspaceSlot($user->id));
    }
}
