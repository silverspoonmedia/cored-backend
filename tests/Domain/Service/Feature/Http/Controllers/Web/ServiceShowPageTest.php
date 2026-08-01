<?php

namespace Tests\Domain\Service\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithWebAuth;
use Tests\Concerns\SeedsCurrencies;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ServiceShowPageTest extends TestCase
{
    use InteractsWithWebAuth;
    use RefreshDatabase;
    use SeedsCurrencies;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seedCurrencies();
    }

    public function test_main_plan_show_page_renders_checkout_without_quantity_input(): void
    {
        $user = User::factory()->create()->assignRole('User');

        $service = Service::factory()->fileHostingMain1Tb()->create();
        ServicePrice::factory()->usd(24.95)->create(['service_id' => $service->id]);

        $this->fakeServiceShowApi($service, planKind: 'main');

        $this->actingWithJwtCookie($user)
            ->get('/apps/services/'.$service->id)
            ->assertOk()
            ->assertDontSee('name="quantity"', false);
    }

    public function test_addon_plan_show_page_renders_quantity_input(): void
    {
        $user = User::factory()->create()->assignRole('User');

        $service = Service::factory()->fileHostingAddon100Gb()->create();
        ServicePrice::factory()->usd(5)->create(['service_id' => $service->id]);

        $this->fakeServiceShowApi($service, planKind: 'addon');

        $this->actingWithJwtCookie($user)
            ->get('/apps/services/'.$service->id)
            ->assertOk()
            ->assertSee('name="quantity"', false);
    }

    private function fakeServiceShowApi(Service $service, string $planKind): void
    {
        Http::fake([
            "*/api/v1/services/{$service->id}" => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $service->id,
                    'code' => $service->code,
                    'name' => $service->name,
                    'description' => $service->description,
                    'billing_interval' => 'monthly',
                    'fulfillment_target' => 'file_hosting',
                    'entitlement' => 'storage',
                    'plan_kind' => $planKind,
                    'storage_bytes' => $service->storage_bytes ?? 0,
                    'storage_bytes_human' => '1 TB',
                    'status' => 'active',
                    'prices' => [
                        [
                            'currency' => 'USD',
                            'amount' => 24.95,
                            'amount_formatted' => '$24.95',
                        ],
                    ],
                ],
            ], 200),
        ]);
    }
}
