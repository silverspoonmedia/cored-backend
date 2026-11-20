<?php

namespace Tests\Domain\Core\Service\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Tests\Support\TestPlans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsCurrencies;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ServicePurchaseQuantityApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCurrencies;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seedCurrencies();
    }

    public function test_pending_addon_quantity_update_reprices_invoice(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['IDR' => 18000],
            ], 200),
        ]);

        $user = $this->userWithRole('User');
        $service = Service::factory()->plan(TestPlans::addon100Gb())->create();
        ServicePrice::factory()->usd(2.95)->create(['service_id' => $service->id]);

        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'quantity' => 1,
            'display_currency' => 'USD',
            'display_amount' => 2.95,
            'list_amount' => 2.95,
            'charge_amount' => 2.95,
            'settlement_currency' => 'IDR',
            'settlement_amount' => 53100,
            'fx_rate' => 18000,
        ]);

        $invoice = Invoice::query()->create([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => null,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'external_transaction_id' => null,
            'payment_method' => 'pending',
            'subtotal' => 53100,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 53100,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $response = $this->actingAsJwt($user)
            ->patchJson('/api/v1/service-purchases/'.$purchase->id.'/quantity', ['quantity' => 3])
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame(3, $data['quantity']);
        $this->assertArrayNotHasKey('settlement_amount', $data);
        $this->assertEquals(159300.0, (float) $data['invoice']['subtotal']);

        $purchase->refresh();
        $invoice->refresh();

        $this->assertSame(3, (int) $purchase->quantity);
        $this->assertEquals(8.85, (float) $purchase->charge_amount);
        $this->assertEquals(159300.0, (float) $purchase->settlement_amount);
        $this->assertEquals(159300.0, (float) $invoice->subtotal);
    }

    public function test_main_plan_quantity_update_is_rejected(): void
    {
        $user = $this->userWithRole('User');
        $service = Service::factory()->plan(TestPlans::main1Tb())->create();
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
        ]);

        Invoice::query()->create([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => null,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'external_transaction_id' => null,
            'payment_method' => 'pending',
            'subtotal' => 100000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 100000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/service-purchases/'.$purchase->id.'/quantity', ['quantity' => 2])
            ->assertStatus(422);
    }

    public function test_paid_purchase_quantity_update_is_rejected(): void
    {
        $user = $this->userWithRole('User');
        $service = Service::factory()->plan(TestPlans::addon100Gb())->create();
        $purchase = ServicePurchase::factory()->paid()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'status' => PurchaseStatus::Paid,
        ]);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/service-purchases/'.$purchase->id.'/quantity', ['quantity' => 2])
            ->assertStatus(422);
    }

    public function test_quantity_update_clears_checkout_channel_session(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['IDR' => 18000],
            ], 200),
        ]);

        $user = $this->userWithRole('User');
        $service = Service::factory()->plan(TestPlans::addon100Gb())->create();
        ServicePrice::factory()->usd(2.95)->create(['service_id' => $service->id]);

        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'quantity' => 1,
            'display_currency' => 'USD',
            'charge_amount' => 2.95,
            'list_amount' => 2.95,
            'settlement_currency' => 'IDR',
            'settlement_amount' => 53100,
        ]);

        $invoice = Invoice::query()->create([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => null,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'external_transaction_id' => 'ext-123',
            'payment_method' => 'va_bca',
            'payment_channel_code' => 'va_bca',
            'subtotal' => 53100,
            'payment_channel_fee' => 3500,
            'customer_gross_total' => 56600,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/service-purchases/'.$purchase->id.'/quantity', ['quantity' => 2])
            ->assertOk();

        $invoice->refresh();

        $this->assertNull($invoice->payment_channel_code);
        $this->assertNull($invoice->external_transaction_id);
        $this->assertEquals(0.0, (float) $invoice->payment_channel_fee);
        $this->assertEquals(106200.0, (float) $invoice->subtotal);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
