<?php

namespace Tests\Domain\PaymentGateway\Unit;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Support\DataTable\InvoiceRowActionResolver;
use App\Domain\PaymentGateway\Support\DataTable\PaymentGatewayRowActionResolver;
use App\Domain\PaymentGateway\Support\DataTable\SubscriptionRowActionResolver;
use App\Domain\PaymentGateway\Enums\BillingCycle;
use App\Domain\PaymentGateway\Enums\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataTableRowActionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_gateway_resolver_generates_valid_api_routes(): void
    {
        $gateway = PaymentGateway::query()->create([
            'name' => 'midtrans',
            'display_name' => 'Midtrans',
            'is_active' => true,
            'priority' => 10,
        ]);

        $actions = app(PaymentGatewayRowActionResolver::class)->resolve($gateway);

        $this->assertCount(3, $actions);
        $this->assertStringContainsString('/api/v1/payment-gateways/'.$gateway->id, $actions[0]->url);
    }

    public function test_subscription_resolver_generates_valid_api_routes(): void
    {
        $subscription = Subscription::query()->create([
            'subscription_number' => 'SUB-TEST-001',
            'subscriber_name' => 'Test User',
            'subscriber_email' => 'test@example.com',
            'plan_name' => 'Pro',
            'billing_cycle' => BillingCycle::Monthly,
            'amount' => 100000,
            'currency' => 'IDR',
            'status' => SubscriptionStatus::Trial,
        ]);

        $actions = app(SubscriptionRowActionResolver::class)->resolve($subscription);

        $this->assertCount(3, $actions);
        $this->assertStringContainsString('/api/v1/subscriptions/'.$subscription->id, $actions[0]->url);
    }

    public function test_invoice_resolver_generates_valid_api_routes(): void
    {
        $gateway = PaymentGateway::query()->create([
            'name' => 'ipaymu',
            'display_name' => 'iPaymu',
            'is_active' => true,
            'priority' => 20,
        ]);

        $subscription = Subscription::query()->create([
            'subscription_number' => 'SUB-TEST-002',
            'subscriber_name' => 'Invoice User',
            'subscriber_email' => 'invoice@example.com',
            'plan_name' => 'Basic',
            'billing_cycle' => BillingCycle::Monthly,
            'amount' => 50000,
            'currency' => 'IDR',
            'status' => SubscriptionStatus::Trial,
        ]);

        $invoice = Invoice::query()->create([
            'invoiceable_id' => $subscription->id,
            'invoiceable_type' => 'subscription',
            'payment_gateway_id' => $gateway->id,
            'invoice_number' => 'INV-TEST-001',
            'payment_method' => 'pending',
            'subtotal' => 50000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 50000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $actions = app(InvoiceRowActionResolver::class)->resolve($invoice);

        $this->assertCount(1, $actions);
        $this->assertStringContainsString('/api/v1/invoices/'.$invoice->id, $actions[0]->url);
    }
}
