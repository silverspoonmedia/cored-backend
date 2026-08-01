<?php

namespace Tests\Domain\Service\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Exceptions\ServiceException;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Services\ServiceCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsCurrencies;
use Tests\TestCase;

class ServiceCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCurrencies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCurrencies();
    }

    public function test_checkout_creates_purchase_and_idr_invoice_with_fx_snapshot(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['IDR' => 18000],
            ], 200),
        ]);

        $user = User::factory()->create();
        $service = Service::factory()->fileHostingMonthly()->create();
        ServicePrice::factory()->usd(10)->create(['service_id' => $service->id]);

        $result = app(ServiceCheckoutService::class)->checkout($service, $user, 'USD');

        $purchase = $result['purchase'];
        $invoice = $result['invoice'];

        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertSame('USD', $purchase->display_currency);
        $this->assertEquals(10.0, (float) $purchase->display_amount);
        $this->assertSame('IDR', $purchase->settlement_currency);
        $this->assertEquals(180000.0, (float) $purchase->settlement_amount);
        $this->assertEquals(18000.0, (float) $purchase->fx_rate);
        $this->assertSame('frankfurter', $purchase->fx_provider);
        $this->assertNotNull($purchase->fx_rate_snapshot_id);
        $this->assertDatabaseHas('exchange_rate_snapshots', [
            'id' => $purchase->fx_rate_snapshot_id,
            'base_currency' => 'USD',
            'quote_currency' => 'IDR',
        ]);

        $this->assertSame('service_purchase', $invoice->invoiceable_type);
        $this->assertSame($purchase->id, $invoice->invoiceable_id);
        $this->assertSame('IDR', $invoice->currency);
        $this->assertEquals(180000.0, (float) $invoice->customer_gross_total);
        $this->assertSame('pending', $invoice->status);
    }

    public function test_addon_checkout_with_quantity_multiplies_amounts(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['IDR' => 18000],
            ], 200),
        ]);

        $user = User::factory()->create();
        $service = Service::factory()->fileHostingAddon100Gb()->create();
        ServicePrice::factory()->usd(5)->create(['service_id' => $service->id]);

        $result = app(ServiceCheckoutService::class)->checkout($service, $user, 'USD', 3);

        $purchase = $result['purchase'];
        $invoice = $result['invoice'];

        $this->assertSame(3, (int) $purchase->quantity);
        $this->assertEquals(15.0, (float) $purchase->display_amount);
        $this->assertEquals(15.0, (float) $purchase->list_amount);
        $this->assertEquals(270000.0, (float) $purchase->settlement_amount);
        $this->assertEquals(270000.0, (float) $invoice->subtotal);
    }

    public function test_main_plan_checkout_rejects_quantity_greater_than_one(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->fileHostingMonthly()->create();
        ServicePrice::factory()->usd(10)->create(['service_id' => $service->id]);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage(__('api/services.quantity_main_plan_fixed'));

        app(ServiceCheckoutService::class)->checkout($service, $user, 'USD', 2);
    }

    public function test_checkout_rejects_inactive_service(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->draft()->create();
        ServicePrice::factory()->usd(10)->create(['service_id' => $service->id]);

        $this->expectException(ServiceException::class);

        app(ServiceCheckoutService::class)->checkout($service, $user, 'USD');
    }

    public function test_checkout_rejects_missing_currency_price(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $service = Service::factory()->fileHostingMonthly()->create();
        ServicePrice::factory()->usd(10)->create(['service_id' => $service->id]);

        $this->expectException(ServiceException::class);

        app(ServiceCheckoutService::class)->checkout($service, $user, 'EUR');
    }
}
