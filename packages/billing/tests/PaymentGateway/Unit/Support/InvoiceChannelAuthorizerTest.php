<?php

namespace App\Domain\PaymentGateway\Tests\Unit\Support;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Support\InvoiceChannelAuthorizer;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class InvoiceChannelAuthorizerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_owner_can_subscribe(): void
    {
        $owner = $this->userWithRole('User', 'owner@example.com');
        $invoice = $this->invoiceForUser($owner);

        $this->assertTrue(app(InvoiceChannelAuthorizer::class)->canSubscribe($owner, $invoice));
    }

    public function test_stranger_cannot_subscribe(): void
    {
        $owner = $this->userWithRole('User', 'owner2@example.com');
        $stranger = $this->userWithRole('User', 'stranger@example.com');
        $invoice = $this->invoiceForUser($owner);

        $this->assertFalse(app(InvoiceChannelAuthorizer::class)->canSubscribe($stranger, $invoice));
    }

    public function test_staff_with_view_invoice_can_subscribe(): void
    {
        $owner = $this->userWithRole('User', 'owner3@example.com');
        $admin = $this->userWithRole('Admin', 'admin-pg@example.com');
        $invoice = $this->invoiceForUser($owner);

        $this->assertTrue($admin->can('view invoice'));
        $this->assertTrue(app(InvoiceChannelAuthorizer::class)->canSubscribe($admin, $invoice));
    }

    private function invoiceForUser(User $user): Invoice
    {
        $purchase = ServicePurchase::factory()->pending()->create(['user_id' => $user->id]);

        return Invoice::query()->create([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => null,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'pending',
            'subtotal' => 50000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 50000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }
}
