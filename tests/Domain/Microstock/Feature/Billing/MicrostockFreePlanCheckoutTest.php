<?php

namespace Tests\Domain\Microstock\Feature\Billing;

use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Support\MicrostockCatalog;
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
use Tests\Concerns\SeedsCurrencies;
use Tests\TestCase;

/**
 * Microstock's free registration and slot addons exercised through the billing
 * package. Lives with the product because the SKUs are Microstock knowledge.
 */
class MicrostockFreePlanCheckoutTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCurrencies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCurrencies();
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
        $this->assertSame(2, $entitlements->addonSlotQuantity(
            $user->id,
            MicrostockCatalog::FULFILLMENT_TARGET,
            MicrostockCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
        ));
        $this->assertTrue($entitlements->hasSlotEntitlement(
            $user->id,
            MicrostockCatalog::FULFILLMENT_TARGET,
            MicrostockCatalog::ENTITLEMENT_WORKSPACE_SLOTS,
        ));
    }
}
