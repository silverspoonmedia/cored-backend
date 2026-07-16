<?php

namespace Tests\Domain\Service\Feature\Http\Controllers\API\V1;

use App\Domain\FileHosting\Services\QuotaService;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seedCurrencies();
        config(['file-hosting.default_quota_bytes' => 5 * 1024 * 1024 * 1024]);
    }

    public function test_checkout_endpoint_creates_purchase_and_pending_invoice(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['IDR' => 18000],
            ], 200),
        ]);

        $user = $this->userWithRole('User');
        $service = Service::factory()->fileHostingMonthly()->create();
        ServicePrice::factory()->usd(10)->create(['service_id' => $service->id]);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/services/'.$service->id.'/checkout', ['currency' => 'USD'])
            ->assertCreated()
            ->assertJsonPath('data.display_currency', 'USD')
            ->assertJsonPath('data.display_amount', 10)
            ->assertJsonPath('data.settlement_currency', 'IDR')
            ->assertJsonPath('data.settlement_amount', 180000)
            ->assertJsonPath('data.invoice.currency', 'IDR')
            ->assertJsonPath('data.invoice.status', 'pending');

        $this->assertDatabaseHas('service_purchases', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'pending',
        ]);
    }

    public function test_paid_sync_fulfills_unlimited_file_hosting_quota(): void
    {
        $user = $this->userWithRole('User');
        $service = Service::factory()->fileHostingMonthly()->create();
        ServicePrice::factory()->usd(10)->create(['service_id' => $service->id]);

        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'settlement_amount' => 180000,
        ]);

        $invoice = Invoice::query()->create([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => null,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'external_transaction_id' => null,
            'payment_method' => 'pending',
            'subtotal' => 180000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 180000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $quotaBefore = app(QuotaService::class)->usage($user->id);
        $this->assertSame(5 * 1024 * 1024 * 1024, $quotaBefore['quota_bytes']);

        app(MidtransInvoiceSynchronizer::class)->sync($invoice, [
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
            'transaction_id' => 'mid-txn-1',
            'gross_amount' => '180000.00',
            'settlement_time' => now()->toDateTimeString(),
        ]);

        $purchase->refresh();
        $this->assertSame(PurchaseStatus::Paid, $purchase->status);
        $this->assertNotNull($purchase->period_start);
        $this->assertNotNull($purchase->period_end);

        $quotaAfter = app(QuotaService::class)->usage($user->id);
        $this->assertSame(0, $quotaAfter['quota_bytes']);
        $this->assertNull($quotaAfter['remaining_bytes']);
        $this->assertSame('unlimited', $quotaAfter['state']);

        $this->assertDatabaseHas('file_hosting_user_quotas', [
            'user_id' => $user->id,
            'quota_bytes' => 0,
            'source' => 'service_purchase',
            'source_id' => $purchase->id,
        ]);

        $this->assertDatabaseHas('service_entitlements', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'active',
            'source_purchase_id' => $purchase->id,
        ]);

        // Unlimited: assertCanAccept does not throw even far above former default.
        app(QuotaService::class)->assertCanAccept($user->id, 50 * 1024 * 1024 * 1024);
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
