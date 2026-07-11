<?php

namespace Tests\Domain\PaymentGateway\Feature;

use App\Domain\PaymentGateway\Enums\BillingCycle;
use App\Domain\PaymentGateway\Enums\SubscriptionStatus;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;

    private PaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionService::class);
        $this->gateway = PaymentGateway::query()->create([
            'name' => 'midtrans',
            'display_name' => 'Midtrans',
            'is_active' => true,
            'priority' => 10,
        ]);
    }

    public function test_list_returns_subscriptions(): void
    {
        Subscription::query()->create($this->subscriptionAttributes());
        Subscription::query()->create($this->subscriptionAttributes([
            'subscriber_email' => 'second@example.com',
        ]));

        $subscriptions = $this->service->list();

        $this->assertCount(2, $subscriptions);
    }

    public function test_create_with_payment_gateway_creates_pending_invoice(): void
    {
        $subscription = $this->service->create($this->subscriptionAttributes([
            'payment_gateway_id' => $this->gateway->id,
        ]));

        $subscription->load('invoice');

        $this->assertNotNull($subscription->invoice);
        $this->assertSame('pending', $subscription->invoice->status);
        $this->assertSame('subscription', $subscription->invoice->invoiceable_type);
        $this->assertSame($subscription->id, $subscription->invoice->invoiceable_id);
        $this->assertSame($this->gateway->id, $subscription->invoice->payment_gateway_id);
        $this->assertSame($subscription->amount, $subscription->invoice->subtotal);
    }

    public function test_create_without_payment_gateway_creates_pending_invoice(): void
    {
        $subscription = $this->service->create($this->subscriptionAttributes());

        $subscription->load('invoice');

        $this->assertNotNull($subscription->invoice);
        $this->assertSame('pending', $subscription->invoice->status);
        $this->assertSame('subscription', $subscription->invoice->invoiceable_type);
        $this->assertSame($subscription->id, $subscription->invoice->invoiceable_id);
        $this->assertNull($subscription->invoice->payment_gateway_id);
        $this->assertSame($subscription->amount, $subscription->invoice->subtotal);
    }

    public function test_delete_removes_pending_invoice(): void
    {
        $subscription = $this->service->create($this->subscriptionAttributes([
            'payment_gateway_id' => $this->gateway->id,
        ]));

        $invoiceId = $subscription->invoice->id;

        $this->service->delete($subscription->id);

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoiceId]);
    }

    public function test_delete_keeps_paid_invoice(): void
    {
        $subscription = $this->service->create($this->subscriptionAttributes([
            'payment_gateway_id' => $this->gateway->id,
        ]));

        $subscription->invoice->update(['status' => 'paid']);

        $this->service->delete($subscription->id);

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseHas('invoices', [
            'id' => $subscription->invoice->id,
            'status' => 'paid',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function subscriptionAttributes(array $overrides = []): array
    {
        return array_merge([
            'subscription_number' => Subscription::generateSubscriptionNumber(),
            'subscriber_name' => 'Jane Doe',
            'subscriber_email' => 'jane@example.com',
            'plan_name' => 'Pro Plan',
            'billing_cycle' => BillingCycle::Monthly,
            'amount' => 99000,
            'currency' => 'IDR',
            'status' => SubscriptionStatus::Trial,
        ], $overrides);
    }
}
