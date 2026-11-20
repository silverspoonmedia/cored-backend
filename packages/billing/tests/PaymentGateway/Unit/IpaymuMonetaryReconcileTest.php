<?php

namespace App\Domain\PaymentGateway\Tests\Unit;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuInvoiceSynchronizer;
use App\Domain\PaymentGateway\Tests\TestCase;
use Illuminate\Support\Str;

class IpaymuMonetaryReconcileTest extends TestCase
{
    private IpaymuInvoiceSynchronizer $synchronizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->synchronizer = app(IpaymuInvoiceSynchronizer::class);
    }

    public function test_exact_subtotal_updates_fee_and_gross(): void
    {
        $invoice = $this->pendingInvoice(181975.0);

        $this->synchronizer->reconcileMonetaryFieldsForTest($invoice, [
            'sub_total' => 181975,
            'fee' => 1274,
            'amount' => 183249,
        ]);

        $this->assertSame(181975.0, (float) $invoice->subtotal);
        $this->assertSame(1274.0, (float) $invoice->payment_channel_fee);
        $this->assertSame(183249.0, (float) $invoice->customer_gross_total);
    }

    public function test_subtotal_within_one_idr_epsilon_is_accepted(): void
    {
        $invoice = $this->pendingInvoice(181975.0);

        $this->synchronizer->reconcileMonetaryFieldsForTest($invoice, [
            'SubTotal' => 181975.5,
            'Fee' => 1273.5,
            'Amount' => 183249,
        ]);

        $this->assertSame(181975.0, (float) $invoice->subtotal);
        $this->assertSame(1273.5, (float) $invoice->payment_channel_fee);
        $this->assertSame(183249.0, (float) $invoice->customer_gross_total);
    }

    public function test_subtotal_mismatch_beyond_epsilon_throws(): void
    {
        $invoice = $this->pendingInvoice(181975.0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('iPaymu SubTotal does not match invoice subtotal.');

        $this->synchronizer->reconcileMonetaryFieldsForTest($invoice, [
            'sub_total' => 180000,
            'fee' => 1274,
            'amount' => 181274,
        ]);
    }

    public function test_amount_not_equal_subtotal_plus_fee_throws(): void
    {
        $invoice = $this->pendingInvoice(181975.0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('iPaymu Amount does not match SubTotal plus channel fee.');

        $this->synchronizer->reconcileMonetaryFieldsForTest($invoice, [
            'sub_total' => 181975,
            'fee' => 1274,
            'amount' => 999999,
        ]);
    }

    public function test_paid_off_alias_used_as_subtotal(): void
    {
        $invoice = $this->pendingInvoice(181975.0);

        $this->synchronizer->reconcileMonetaryFieldsForTest($invoice, [
            'paid_off' => 181975,
            'fee' => 1274,
            'total' => 183249,
        ]);

        $this->assertSame(1274.0, (float) $invoice->payment_channel_fee);
        $this->assertSame(183249.0, (float) $invoice->customer_gross_total);
    }

    /**
     * Bug: charge sent customer_gross_total as amount → iPaymu SubTotal = old gross, not sale.
     * INV-20260716-KW6ADB: subtotal 180710, gross 181975, api SubTotal 181975, fee 1274, amount 183249.
     */
    public function test_stale_gross_as_ipaymu_subtotal_self_heals(): void
    {
        $invoice = $this->pendingInvoice(180710.0, 1265.0, 181975.0);

        $this->synchronizer->reconcileMonetaryFieldsForTest($invoice, [
            'SubTotal' => 181975,
            'Fee' => 1274,
            'Amount' => 183249,
        ]);

        $this->assertSame(180710.0, (float) $invoice->subtotal);
        $this->assertSame(1274.0, (float) $invoice->payment_channel_fee);
        $this->assertSame(183249.0, (float) $invoice->customer_gross_total);
    }

    private function pendingInvoice(float $subtotal, float $fee = 0.0, ?float $gross = null): Invoice
    {
        return Invoice::query()->create([
            'invoiceable_id' => (string) Str::uuid(),
            'invoiceable_type' => 'service_purchase',
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'pending',
            'subtotal' => $subtotal,
            'payment_channel_fee' => $fee,
            'customer_gross_total' => $gross ?? $subtotal,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);
    }
}
