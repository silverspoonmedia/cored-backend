<?php

namespace Tests\Domain\PaymentGateway\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithWebAuth;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class InvoiceWebNavigationTest extends TestCase
{
    use InteractsWithWebAuth;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_invoice_show_redirects_service_purchase_to_purchase_hub(): void
    {
        $purchaseId = (string) Str::uuid();
        $invoiceId = (string) Str::uuid();

        Http::fake([
            "*/api/v1/invoices/{$invoiceId}" => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $invoiceId,
                    'invoiceable_type' => 'service_purchase',
                    'invoiceable_id' => $purchaseId,
                    'subtotal' => 100000,
                    'currency' => 'IDR',
                ],
            ], 200),
        ]);

        $admin = $this->actingWithJwtCookie(User::factory()->create()->assignRole('Admin'));

        $admin->get(route('invoices.show', $invoiceId))
            ->assertRedirect(route('service-purchases.show', $purchaseId));
    }

    public function test_invoice_datatable_view_action_links_to_purchase_hub(): void
    {
        $purchaseId = (string) Str::uuid();
        $invoiceId = (string) Str::uuid();
        $gateway = PaymentGateway::query()->create([
            'name' => 'midtrans',
            'display_name' => 'Midtrans',
            'is_active' => true,
            'priority' => 10,
        ]);

        Invoice::query()->create([
            'id' => $invoiceId,
            'invoiceable_id' => $purchaseId,
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => $gateway->id,
            'invoice_number' => 'INV-TEST-'.strtoupper(Str::random(6)),
            'payment_method' => 'pending',
            'subtotal' => 100000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 100000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        Http::fake([
            '*/api/v1/invoices*' => Http::response([
                'draw' => 1,
                'recordsTotal' => 1,
                'recordsFiltered' => 1,
                'data' => [
                    [
                        'id' => $invoiceId,
                        'invoiceable_type' => 'service_purchase',
                        'invoiceable_id' => $purchaseId,
                        'invoice_number' => 'INV-TEST',
                        'status' => 'pending',
                        'actions' => [
                            ['key' => 'show'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $admin = $this->actingWithJwtCookie(User::factory()->create()->assignRole('Admin'));

        $response = $admin->getJson(route('invoices.datatable', ['draw' => 1]));

        $response->assertOk()
            ->assertJsonPath('data.0.actions.0.url', route('service-purchases.show', $purchaseId));
    }
}
