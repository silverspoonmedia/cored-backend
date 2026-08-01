<?php

namespace Tests\Domain\PaymentGateway\Unit\Support;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Support\InvoiceWebDestination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceWebDestinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_url_points_to_purchase_hub_for_service_purchase(): void
    {
        $purchaseId = (string) Str::uuid();
        $invoiceId = (string) Str::uuid();

        $url = InvoiceWebDestination::showUrl([
            'id' => $invoiceId,
            'invoiceable_type' => 'service_purchase',
            'invoiceable_id' => $purchaseId,
        ]);

        $this->assertSame(route('service-purchases.show', $purchaseId), $url);
    }

    public function test_show_url_points_to_invoice_show_for_other_morphs(): void
    {
        $invoiceId = (string) Str::uuid();

        $url = InvoiceWebDestination::showUrl([
            'id' => $invoiceId,
            'invoiceable_type' => 'subscription',
            'invoiceable_id' => (string) Str::uuid(),
        ]);

        $this->assertSame(route('invoices.show', $invoiceId), $url);
    }

    public function test_show_url_works_with_invoice_model(): void
    {
        $purchaseId = (string) Str::uuid();

        $invoice = Invoice::make([
            'id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'invoiceable_id' => $purchaseId,
        ]);

        $this->assertSame(route('service-purchases.show', $purchaseId), InvoiceWebDestination::showUrl($invoice));
    }
}
