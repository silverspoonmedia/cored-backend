<?php

namespace App\Domain\PaymentGateway\Tests\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class InvoiceResourceHygieneTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_buyer_nested_invoice_omits_staff_fields(): void
    {
        $buyer = $this->userWithRole('User');
        $purchase = ServicePurchase::factory()->pending()->create([
            'user_id' => $buyer->id,
        ]);
        $this->attachInvoice($purchase, 'secret-trx-buyer');

        $invoice = $this->actingAsJwt($buyer)
            ->getJson('/api/v1/service-purchases/'.$purchase->id)
            ->assertOk()
            ->json('data.invoice');

        $this->assertIsArray($invoice);
        $this->assertArrayHasKey('checkout_session', $invoice);
        $this->assertArrayNotHasKey('external_transaction_id', $invoice);
        $this->assertArrayNotHasKey('invoiceable_type', $invoice);
        $this->assertArrayNotHasKey('payment_gateway_id', $invoice);
        $this->assertArrayNotHasKey('payment_channel_route_id', $invoice);
        $this->assertArrayNotHasKey('payment_channel_route_snapshot_id', $invoice);
        $this->assertArrayNotHasKey('payment_channel_route', $invoice);
        $this->assertArrayNotHasKey('payment_channel_route_snapshot', $invoice);
        $this->assertArrayNotHasKey('disbursement_status', $invoice);
        $this->assertArrayNotHasKey('withdrawable_at', $invoice);
        $this->assertArrayNotHasKey('disbursed_at', $invoice);
    }

    public function test_admin_invoice_show_includes_staff_fields(): void
    {
        $admin = $this->userWithRole('Admin');
        $purchase = ServicePurchase::factory()->pending()->create();
        $invoice = $this->attachInvoice($purchase, 'secret-trx-admin');

        $data = $this->actingAsJwt($admin)
            ->getJson('/api/v1/invoices/'.$invoice->id)
            ->assertOk()
            ->json('data');

        $this->assertSame('secret-trx-admin', $data['external_transaction_id']);
        $this->assertSame('service_purchase', $data['invoiceable_type']);
        $this->assertArrayHasKey('disbursement_status', $data);
    }

    private function attachInvoice(ServicePurchase $purchase, string $externalId): Invoice
    {
        return Invoice::query()->create([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'external_transaction_id' => $externalId,
            'payment_method' => 'qris',
            'subtotal' => 10_000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 10_000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
