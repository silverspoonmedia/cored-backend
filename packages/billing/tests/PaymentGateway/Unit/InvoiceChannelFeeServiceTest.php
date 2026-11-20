<?php

namespace App\Domain\PaymentGateway\Tests\Unit;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Models\PaymentChannelRouteSnapshot;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\Payment\InvoiceChannelFeeService;
use App\Domain\PaymentGateway\Tests\Concerns\SetsUpPaymentChannels;
use App\Domain\PaymentGateway\Tests\TestCase;
use Illuminate\Support\Str;

class InvoiceChannelFeeServiceTest extends TestCase
{
    use SetsUpPaymentChannels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPaymentGatewaysAndChannels();
    }

    public function test_estimates_flat_fee_from_route(): void
    {
        $route = PaymentChannelRoute::query()
            ->whereHas('paymentChannel', fn ($q) => $q->where('code', 'va_bca'))
            ->whereHas('paymentGateway', fn ($q) => $q->where('name', 'ipaymu'))
            ->firstOrFail();

        $service = app(InvoiceChannelFeeService::class);

        $this->assertSame(3500.0, $service->estimate($route, 250_000));
    }

    public function test_applies_midtrans_fee_before_charge(): void
    {
        $route = PaymentChannelRoute::query()
            ->whereHas('paymentChannel', fn ($q) => $q->where('code', 'qris'))
            ->whereHas('paymentGateway', fn ($q) => $q->where('name', 'midtrans'))
            ->firstOrFail();

        $invoice = $this->createInvoice(['subtotal' => 100_000]);

        app(InvoiceChannelFeeService::class)->applyBeforeCharge($invoice, $route, 'midtrans');

        $invoice->refresh();
        $this->assertSame(700.0, (float) $invoice->payment_channel_fee);
        $this->assertSame(100_700.0, (float) $invoice->customer_gross_total);
    }

    public function test_reset_for_fresh_checkout_clears_fee_and_route(): void
    {
        $route = PaymentChannelRoute::query()
            ->with('paymentChannel')
            ->firstOrFail();
        $snapshot = PaymentChannelRouteSnapshot::fromRoute($route, (string) $route->paymentChannel->code);

        $invoice = $this->createInvoice([
            'subtotal' => 100_000,
            'payment_channel_fee' => 3500,
            'customer_gross_total' => 103_500,
            'payment_channel_route_id' => $route->id,
            'payment_channel_route_snapshot_id' => $snapshot->id,
        ]);

        app(InvoiceChannelFeeService::class)->resetForFreshCheckout($invoice);
        $invoice->save();

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->payment_channel_fee);
        $this->assertSame(100_000.0, (float) $invoice->customer_gross_total);
        $this->assertNull($invoice->payment_channel_route_id);
        $this->assertNull($invoice->payment_channel_route_snapshot_id);
    }

    public function test_quote_for_channel_uses_resolver_primary_route(): void
    {
        $channel = PaymentChannel::query()->where('code', 'va_bca')->firstOrFail();
        $quote = app(InvoiceChannelFeeService::class)->quoteForChannel($channel, 250_000);

        $this->assertNotNull($quote);
        $this->assertSame(3500.0, $quote['estimated_channel_fee']);
        $this->assertSame(253_500.0, $quote['estimated_customer_total']);
        $this->assertSame('ipaymu', $quote['gateway_name']);
        $this->assertSame('flat', $quote['fee_type']);
    }

    public function test_quote_for_percent_channel_includes_fee_basis(): void
    {
        $channel = PaymentChannel::query()->where('code', 'qris')->firstOrFail();
        $quote = app(InvoiceChannelFeeService::class)->quoteForChannel($channel, 250_000);

        $this->assertNotNull($quote);
        $this->assertSame('percent', $quote['fee_type']);
        $this->assertSame(0.7, (float) $quote['fee_percent']);
        $this->assertSame('0.7%', $quote['fee_label']);
        $this->assertStringContainsString('0.7%', (string) $quote['fee_basis']);
    }

    public function test_money_breakdown_includes_percent_fee_basis_from_route(): void
    {
        $route = PaymentChannelRoute::query()
            ->whereHas('paymentChannel', fn ($q) => $q->where('code', 'qris'))
            ->whereHas('paymentGateway', fn ($q) => $q->where('name', 'midtrans'))
            ->firstOrFail();

        $snapshot = PaymentChannelRouteSnapshot::fromRoute($route, 'qris');

        $invoice = $this->createInvoice([
            'payment_channel_route_id' => $route->id,
            'payment_channel_route_snapshot_id' => $snapshot->id,
            'payment_channel_fee' => 700,
            'customer_gross_total' => 100_700,
        ]);

        $breakdown = app(InvoiceChannelFeeService::class)->moneyBreakdown($invoice);

        $this->assertSame('percent', $breakdown['payment_channel_fee_type']);
        $this->assertSame('0.7%', $breakdown['payment_channel_fee_label']);
        $this->assertStringContainsString('0.7%', (string) $breakdown['payment_channel_fee_basis']);
    }

    public function test_fee_basis_fields_use_snapshot_not_live_route(): void
    {
        $route = PaymentChannelRoute::query()
            ->whereHas('paymentChannel', fn ($q) => $q->where('code', 'qris'))
            ->whereHas('paymentGateway', fn ($q) => $q->where('name', 'midtrans'))
            ->firstOrFail();

        $snapshot = PaymentChannelRouteSnapshot::fromRoute($route, 'qris');
        $route->update(['fee_percent' => 9.9]);

        $basis = app(InvoiceChannelFeeService::class)->feeBasisFields($snapshot);

        $this->assertSame('percent', $basis['payment_channel_fee_type']);
        $this->assertSame('0.7%', $basis['payment_channel_fee_label']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInvoice(array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => PaymentGateway::query()->where('name', 'midtrans')->value('id'),
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'pending',
            'subtotal' => 100_000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 100_000,
            'currency' => 'IDR',
            'status' => 'pending',
        ], $overrides));
    }
}
