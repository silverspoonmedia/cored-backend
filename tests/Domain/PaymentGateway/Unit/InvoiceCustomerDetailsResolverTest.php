<?php

namespace Tests\Domain\PaymentGateway\Unit;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Enums\BillingCycle;
use App\Domain\PaymentGateway\Enums\SubscriptionStatus;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Services\Payment\InvoiceCustomerDetailsResolver;
use App\Domain\Playground\Models\Order;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InvoiceCustomerDetailsResolverTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceCustomerDetailsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payment.default_buyer_phone' => '081000000000']);
        $this->resolver = app(InvoiceCustomerDetailsResolver::class);
    }

    public function test_resolves_service_purchase_from_user_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Alice Buyer',
            'email' => 'alice@example.com',
        ]);
        $purchase = ServicePurchase::factory()->pending()->create(['user_id' => $user->id]);
        $invoice = $this->invoiceFor($purchase->id, 'service_purchase');

        $buyer = $this->resolver->forIpaymu($invoice);

        $this->assertSame('Alice Buyer', $buyer['name']);
        $this->assertSame('alice@example.com', $buyer['email']);
        $this->assertSame('081000000000', $buyer['phone']);
    }

    public function test_midtrans_shape_uses_first_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Bob Midtrans',
            'email' => 'bob@example.com',
        ]);
        $purchase = ServicePurchase::factory()->pending()->create(['user_id' => $user->id]);
        $invoice = $this->invoiceFor($purchase->id, 'service_purchase');

        $details = $this->resolver->forMidtrans($invoice);

        $this->assertSame('Bob Midtrans', $details['first_name']);
        $this->assertSame('bob@example.com', $details['email']);
        $this->assertSame('081000000000', $details['phone']);
    }

    public function test_request_override_wins(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile Name',
            'email' => 'profile@example.com',
        ]);
        $purchase = ServicePurchase::factory()->pending()->create(['user_id' => $user->id]);
        $invoice = $this->invoiceFor($purchase->id, 'service_purchase');

        $buyer = $this->resolver->forIpaymu($invoice, [
            'name' => 'Override Name',
            'email' => 'override@example.com',
            'phone' => '08123456789',
        ]);

        $this->assertSame('Override Name', $buyer['name']);
        $this->assertSame('override@example.com', $buyer['email']);
        $this->assertSame('08123456789', $buyer['phone']);
    }

    public function test_order_invoiceable_customer_fields(): void
    {
        $order = Order::factory()->create([
            'customer_name' => 'Order Customer',
            'customer_email' => 'order@example.com',
            'customer_phone' => '08111111111',
        ]);
        $invoice = $this->invoiceFor($order->id, 'order');

        $buyer = $this->resolver->forIpaymu($invoice);

        $this->assertSame('Order Customer', $buyer['name']);
        $this->assertSame('order@example.com', $buyer['email']);
        $this->assertSame('08111111111', $buyer['phone']);
    }

    public function test_subscription_invoiceable_subscriber_fields(): void
    {
        $subscription = Subscription::query()->create([
            'subscription_number' => 'SUB-'.uniqid(),
            'subscriber_name' => 'Sub Name',
            'subscriber_email' => 'sub@example.com',
            'subscriber_phone' => '08222222222',
            'plan_name' => 'Basic',
            'billing_cycle' => BillingCycle::Monthly,
            'amount' => 50000,
            'currency' => 'IDR',
            'status' => SubscriptionStatus::Active,
            'auto_renew' => true,
        ]);
        $invoice = $this->invoiceFor($subscription->id, 'subscription');

        $buyer = $this->resolver->forIpaymu($invoice);

        $this->assertSame('Sub Name', $buyer['name']);
        $this->assertSame('sub@example.com', $buyer['email']);
        $this->assertSame('08222222222', $buyer['phone']);
    }

    public function test_falls_back_to_authenticated_user_when_invoiceable_has_no_customer(): void
    {
        $user = User::factory()->create([
            'name' => 'Auth User',
            'email' => 'auth@example.com',
        ]);
        Auth::login($user);

        $invoice = Invoice::query()->create([
            'invoiceable_id' => (string) \Illuminate\Support\Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'pending',
            'subtotal' => 10000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 10000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $buyer = $this->resolver->forIpaymu($invoice);

        $this->assertSame('Auth User', $buyer['name']);
        $this->assertSame('auth@example.com', $buyer['email']);
    }

    public function test_throws_when_email_cannot_be_resolved(): void
    {
        $invoice = Invoice::query()->create([
            'invoiceable_id' => (string) \Illuminate\Support\Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'pending',
            'subtotal' => 10000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 10000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $this->expectException(HttpException::class);

        $this->resolver->forIpaymu($invoice);
    }

    private function invoiceFor(string $invoiceableId, string $type): Invoice
    {
        return Invoice::query()->create([
            'invoiceable_id' => $invoiceableId,
            'invoiceable_type' => $type,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'pending',
            'subtotal' => 100000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 100000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);
    }
}
