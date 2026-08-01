<?php

namespace Tests\Domain\PaymentGateway\Unit;

use App\Domain\PaymentGateway\Enums\DisbursementStatus;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\PaymentIncomeAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentIncomeAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_aggregates_paid_invoices_by_paid_at(): void
    {
        $midtransId = PaymentGateway::query()->create([
            'name' => 'midtrans',
            'display_name' => 'Midtrans',
            'is_active' => true,
            'priority' => 1,
        ])->id;

        Invoice::query()->create([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => $midtransId,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'qris',
            'payment_channel_code' => 'qris',
            'subtotal' => 100_000,
            'payment_channel_fee' => 700,
            'customer_gross_total' => 100_700,
            'currency' => 'IDR',
            'status' => 'paid',
            'paid_at' => now()->subDay(),
            'withdrawable_at' => now(),
            'disbursement_status' => DisbursementStatus::Eligible,
        ]);

        Invoice::query()->create([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => $midtransId,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'pending',
            'subtotal' => 50_000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 50_000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $overview = app(PaymentIncomeAnalyticsService::class)->overview([
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'basis' => 'paid_at',
        ]);

        $this->assertSame(100_000.0, $overview['summary']['service_revenue']);
        $this->assertSame(700.0, $overview['summary']['buyer_fees']);
        $this->assertSame(100_700.0, $overview['summary']['cash_collected']);
        $this->assertSame(1, $overview['summary']['invoice_count']);
        $this->assertSame('midtrans', $overview['by_gateway'][0]['label']);
    }

    public function test_overview_filters_by_withdrawable_at_basis(): void
    {
        Invoice::query()->create([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'va',
            'subtotal' => 200_000,
            'payment_channel_fee' => 3500,
            'customer_gross_total' => 203_500,
            'currency' => 'IDR',
            'status' => 'paid',
            'paid_at' => now()->subDays(10),
            'withdrawable_at' => now()->subDay(),
        ]);

        $overview = app(PaymentIncomeAnalyticsService::class)->overview([
            'from' => now()->subDays(2)->toDateString(),
            'to' => now()->toDateString(),
            'basis' => 'withdrawable_at',
        ]);

        $this->assertSame(200_000.0, $overview['summary']['service_revenue']);
        $this->assertSame(1, $overview['summary']['invoice_count']);
    }

    public function test_invoices_paginated_returns_paid_rows(): void
    {
        Invoice::query()->create([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'qris',
            'subtotal' => 75_000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 75_000,
            'currency' => 'IDR',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $paginator = app(PaymentIncomeAnalyticsService::class)->invoicesPaginated([
            'basis' => 'paid_at',
        ], 10);

        $this->assertSame(1, $paginator->total());
        $this->assertSame(75_000.0, (float) $paginator->items()[0]->subtotal);
    }

    public function test_default_filters_use_reporting_timezone_when_utc_is_still_previous_day(): void
    {
        config(['payment.reporting_timezone' => 'Asia/Jakarta']);

        $this->travelTo(Carbon::parse('2026-07-31 20:00:00', 'UTC'));

        Invoice::query()->create([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'qris',
            'subtotal' => 450_397,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 450_397,
            'currency' => 'IDR',
            'status' => 'paid',
            'paid_at' => '2026-08-01 00:22:07',
        ]);

        $service = app(PaymentIncomeAnalyticsService::class);
        $defaults = $service->defaultFilters();

        $this->assertSame('2026-08-01', $defaults['to']);

        $overview = $service->overview($defaults);

        $this->assertSame(1, $overview['summary']['invoice_count']);
        $this->assertSame(450_397.0, $overview['summary']['service_revenue']);
    }
}
