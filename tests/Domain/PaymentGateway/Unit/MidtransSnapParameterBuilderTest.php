<?php

namespace Tests\Domain\PaymentGateway\Unit;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransSnapParameterBuilder;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransSnapParameterBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_includes_customer_details_for_service_purchase(): void
    {
        config(['payment.default_buyer_phone' => '081000000000']);

        $user = User::factory()->create([
            'name' => 'Snap Customer',
            'email' => 'snap@example.com',
        ]);
        $purchase = ServicePurchase::factory()->pending()->create(['user_id' => $user->id]);
        $invoice = Invoice::query()->create([
            'invoiceable_id' => $purchase->id,
            'invoiceable_type' => 'service_purchase',
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'payment_method' => 'pending',
            'subtotal' => 180000,
            'payment_channel_fee' => 0,
            'customer_gross_total' => 180000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $params = app(MidtransSnapParameterBuilder::class)->build($invoice);

        $this->assertSame($invoice->invoice_number, $params['transaction_details']['order_id']);
        $this->assertSame(180000.0, $params['transaction_details']['gross_amount']);
        $this->assertSame('Snap Customer', $params['customer_details']['first_name']);
        $this->assertSame('snap@example.com', $params['customer_details']['email']);
        $this->assertSame('081000000000', $params['customer_details']['phone']);
    }
}
