<?php

namespace Tests\Domain\Archivd\Feature\Billing;

use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Archivd\Support\ArchivdCatalog;
use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransInvoiceSynchronizer;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsCurrencies;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ServiceCheckoutAndFulfillmentTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCurrencies;
    use SeedsRolesAndPermissions;

    private int $free;

    private int $main1Tb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seedCurrencies();
        $this->free = ArchivdCatalog::freeBytes();
        $this->main1Tb = ArchivdCatalog::main1TbBytes();
        config(['archivd.default_quota_bytes' => $this->free]);
    }

    public function test_checkout_endpoint_creates_purchase_and_pending_invoice(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['IDR' => 18000],
            ], 200),
        ]);

        $user = $this->userWithRole('User');
        $service = Service::factory()->archivdMain1Tb()->create();
        ServicePrice::factory()->usd(24.95)->create(['service_id' => $service->id]);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/services/'.$service->id.'/checkout', ['currency' => 'USD'])
            ->assertCreated()
            ->assertJsonPath('data.display_currency', 'USD')
            ->assertJsonPath('data.display_amount', 24.95)
            ->assertJsonPath('data.invoice.currency', 'IDR')
            ->assertJsonPath('data.invoice.status', 'pending');

        $this->assertDatabaseHas('service_purchases', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'pending',
            'settlement_currency' => 'IDR',
        ]);
    }

    public function test_addon_checkout_endpoint_accepts_quantity(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['IDR' => 18000],
            ], 200),
        ]);

        $user = $this->userWithRole('User');
        $service = Service::factory()->archivdAddon100Gb()->create();
        ServicePrice::factory()->usd(5)->create(['service_id' => $service->id]);

        $payload = $this->actingAsJwt($user)
            ->postJson('/api/v1/services/'.$service->id.'/checkout', [
                'currency' => 'USD',
                'quantity' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.display_amount', 15)
            ->json('data');

        $this->assertArrayNotHasKey('settlement_amount', $payload);
        $this->assertDatabaseHas('service_purchases', [
            'id' => $payload['id'],
            'settlement_amount' => 270000,
        ]);
    }

    public function test_main_plan_checkout_rejects_quantity_greater_than_one(): void
    {
        $user = $this->userWithRole('User');
        $service = Service::factory()->archivdMain1Tb()->create();
        ServicePrice::factory()->usd(24.95)->create(['service_id' => $service->id]);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/services/'.$service->id.'/checkout', [
                'currency' => 'USD',
                'quantity' => 2,
            ])
            ->assertUnprocessable();
    }

    public function test_paid_sync_fulfills_main_plan_quota(): void
    {
        $user = $this->userWithRole('User');
        $service = Service::factory()->archivdMain1Tb()->create();
        ServicePrice::factory()->usd(24.95)->create(['service_id' => $service->id]);

        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'settlement_amount' => 449100,
        ]);

        $invoice = Invoice::query()->create([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => null,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'external_transaction_id' => null,
            'payment_method' => 'pending',
            'subtotal' => 449100,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 449100,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $quotaBefore = app(QuotaService::class)->usage($user->id);
        $this->assertSame($this->free, $quotaBefore['quota_bytes']);
        $this->assertSame('free', $quotaBefore['state']);

        app(MidtransInvoiceSynchronizer::class)->sync($invoice, [
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
            'transaction_id' => 'mid-txn-1',
            'gross_amount' => '449100.00',
            'settlement_time' => now()->toDateTimeString(),
        ]);

        $purchase->refresh();
        $this->assertSame(PurchaseStatus::Paid, $purchase->status);
        $this->assertNotNull($purchase->period_start);
        $this->assertNotNull($purchase->period_end);

        $expected = $this->free + $this->main1Tb;
        $quotaAfter = app(QuotaService::class)->usage($user->id);
        $this->assertSame($expected, $quotaAfter['quota_bytes']);
        $this->assertSame($expected, $quotaAfter['remaining_bytes']);
        $this->assertSame('paid', $quotaAfter['state']);

        $this->assertDatabaseHas('archivd_user_quotas', [
            'user_id' => $user->id,
            'quota_bytes' => $expected,
            'source' => 'service_recompute',
        ]);

        $this->assertDatabaseHas('service_entitlements', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'active',
            'quantity' => 1,
            'source_purchase_id' => $purchase->id,
        ]);

        app(QuotaService::class)->assertCanAccept($user->id, $this->main1Tb);
    }

    public function test_purchase_show_for_owner(): void
    {
        $user = $this->userWithRole('User');
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/service-purchases/'.$purchase->id)
            ->assertOk()
            ->assertJsonPath('data.id', $purchase->id);
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
