<?php

namespace App\Domain\Service\Tests\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServiceFulfillmentService;
use App\Domain\Service\Tests\Support\TestPlans;
use App\Domain\Service\Tests\TestCase;

class ServiceEntitlementQuantityFromPurchaseTest extends TestCase
{
    public function test_fulfill_addon_purchase_with_quantity_three_increments_entitlement_by_three(): void
    {
        $user = User::factory()->create();
        $addon = Service::factory()->plan(TestPlans::addon100Gb())->create();

        ServiceEntitlement::query()->create([
            'user_id' => $user->id,
            'service_id' => $addon->id,
            'status' => EntitlementStatus::Active,
            'quantity' => 2,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);

        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $addon->id,
            'quantity' => 3,
        ]);

        app(ServiceFulfillmentService::class)->fulfill($purchase);

        $this->assertDatabaseHas('service_entitlements', [
            'user_id' => $user->id,
            'service_id' => $addon->id,
            'quantity' => 5,
            'status' => EntitlementStatus::Active->value,
        ]);
    }
}
