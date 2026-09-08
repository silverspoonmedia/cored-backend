<?php

namespace Tests\Domain\PaymentGateway\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransPaymentService;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\SeedsCurrencies;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Concerns\SetsUpPaymentChannels;
use Tests\TestCase;

class ServicePurchaseChannelCheckoutQuantityTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCurrencies;
    use SeedsRolesAndPermissions;
    use SetsUpPaymentChannels;

    private ?float $capturedMidtransGross = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seedCurrencies();
        $this->seedPaymentGatewaysAndChannels();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_channel_checkout_commits_addon_quantity_before_midtrans_charge(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([
                'rates' => ['IDR' => 18000],
            ], 200),
        ]);

        $user = $this->userWithRole('Admin');
        $service = Service::factory()->archivdAddon100Gb()->create();
        ServicePrice::factory()->usd(2.95)->create(['service_id' => $service->id]);

        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'quantity' => 1,
            'display_currency' => 'USD',
            'list_amount' => 2.95,
            'charge_amount' => 2.95,
            'display_amount' => 2.95,
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

        $this->partialMock(\App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService::class);

        $this->mock(MidtransPaymentService::class, function ($mock) use ($invoice): void {
            $mock->shouldReceive('charge')
                ->once()
                ->with(Mockery::on(fn (array $input): bool => ($input['invoice_id'] ?? null) === $invoice->id))
                ->andReturnUsing(function (array $input) {
                    $locked = Invoice::query()->findOrFail($input['invoice_id']);
                    $this->capturedMidtransGross = (float) $locked->customer_gross_total;

                    return [
                        'ok' => true,
                        'data' => [
                            'qr_string' => 'midtrans-qris',
                            'actions' => [
                                ['name' => 'generate-qr-code', 'url' => 'https://example.test/qr.png'],
                            ],
                        ],
                        'message_key' => 'api/payments.midtrans.charge_created',
                        'http' => 201,
                    ];
                });
        });

        $this->actingAsJwt($user)
            ->postJson('/api/v1/invoices/'.$invoice->id.'/channel-checkout', [
                'channel_code' => 'qris',
                'quantity' => 3,
            ])
            ->assertCreated();

        $purchase->refresh();
        $invoice->refresh();

        $this->assertSame(3, (int) $purchase->quantity);
        $this->assertEquals(159300.0, (float) $invoice->subtotal);
        $this->assertNotNull($this->capturedMidtransGross);
        $this->assertGreaterThanOrEqual(159300.0, $this->capturedMidtransGross);
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
