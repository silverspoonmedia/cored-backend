<?php

namespace App\Domain\PaymentGateway\Tests\Feature;

use App\Domain\PaymentGateway\Enums\DisbursementStatus;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\Billing\InvoiceDisbursementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceDisbursementServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceDisbursementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InvoiceDisbursementService::class);
    }

    public function test_mark_processing_transitions_eligible_invoice(): void
    {
        $invoice = $this->createInvoice([
            'disbursement_status' => DisbursementStatus::Eligible,
            'withdrawable_at' => now(),
        ]);

        $updated = $this->service->markProcessing($invoice);

        $this->assertSame(DisbursementStatus::Processing, $updated->disbursement_status);
        $this->assertNotNull($updated->disbursement_processing_at);
    }

    public function test_mark_processing_rejects_non_eligible(): void
    {
        $invoice = $this->createInvoice([
            'disbursement_status' => DisbursementStatus::None,
            'withdrawable_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invoice is not eligible for disbursement');

        $this->service->markProcessing($invoice);
    }

    public function test_mark_processing_rejects_missing_withdrawable_at(): void
    {
        $invoice = $this->createInvoice([
            'disbursement_status' => DisbursementStatus::Eligible,
            'withdrawable_at' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invoice has no withdrawable_at');

        $this->service->markProcessing($invoice);
    }

    public function test_mark_completed_from_processing(): void
    {
        $invoice = $this->createInvoice([
            'disbursement_status' => DisbursementStatus::Processing,
            'disbursement_processing_at' => now(),
            'withdrawable_at' => now(),
        ]);

        $updated = $this->service->markCompleted($invoice, 'PAYOUT-REF-001');

        $this->assertSame(DisbursementStatus::Completed, $updated->disbursement_status);
        $this->assertSame('PAYOUT-REF-001', $updated->disbursement_reference);
        $this->assertNotNull($updated->disbursed_at);
    }

    public function test_mark_completed_rejects_empty_reference(): void
    {
        $invoice = $this->createInvoice([
            'disbursement_status' => DisbursementStatus::Processing,
            'disbursement_processing_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('disbursement_reference is required');

        $this->service->markCompleted($invoice, '   ');
    }

    public function test_mark_failed_from_processing(): void
    {
        $invoice = $this->createInvoice([
            'disbursement_status' => DisbursementStatus::Processing,
            'disbursement_processing_at' => now(),
            'withdrawable_at' => now(),
        ]);

        $updated = $this->service->markFailed($invoice, 'bank timeout');

        $this->assertSame(DisbursementStatus::Failed, $updated->disbursement_status);
        $this->assertNull($updated->disbursement_processing_at);
        $this->assertSame('bank timeout', $updated->disbursement_reference);
    }

    public function test_reset_failed_to_eligible_when_withdrawable(): void
    {
        $invoice = $this->createInvoice([
            'disbursement_status' => DisbursementStatus::Failed,
            'disbursement_reference' => 'bank timeout',
            'withdrawable_at' => now(),
        ]);

        $updated = $this->service->resetFailedToEligible($invoice);

        $this->assertSame(DisbursementStatus::Eligible, $updated->disbursement_status);
        $this->assertNull($updated->disbursement_reference);
    }

    public function test_reset_failed_to_none_when_not_withdrawable(): void
    {
        $invoice = $this->createInvoice([
            'disbursement_status' => DisbursementStatus::Failed,
            'disbursement_reference' => 'bank timeout',
            'withdrawable_at' => null,
        ]);

        $updated = $this->service->resetFailedToEligible($invoice);

        $this->assertSame(DisbursementStatus::None, $updated->disbursement_status);
        $this->assertNull($updated->disbursement_reference);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInvoice(array $overrides = []): Invoice
    {
        $gateway = PaymentGateway::query()->create([
            'name' => 'midtrans',
            'display_name' => 'Midtrans',
            'is_active' => true,
            'priority' => 10,
        ]);

        return Invoice::query()->create(array_merge([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'payment_gateway_id' => $gateway->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'bank_transfer',
            'subtotal' => 100000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 100000,
            'currency' => 'IDR',
            'status' => 'paid',
            'disbursement_status' => DisbursementStatus::None,
        ], $overrides));
    }
}
