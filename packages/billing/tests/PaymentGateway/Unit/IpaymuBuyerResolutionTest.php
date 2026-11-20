<?php

namespace App\Domain\PaymentGateway\Tests\Unit;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Domain\PaymentGateway\Tests\TestCase;
use App\Domain\Service\Models\ServicePurchase;
use ReflectionMethod;

class IpaymuBuyerResolutionTest extends TestCase
{
    public function test_resolve_buyer_uses_service_purchase_user_without_request_buyer(): void
    {
        config(['payment.default_buyer_phone' => '081000000000']);

        $user = User::factory()->create([
            'name' => 'Ipaymu User',
            'email' => 'ipaymu@example.com',
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

        $service = app(IpaymuPaymentService::class);
        $method = new ReflectionMethod(IpaymuPaymentService::class, 'resolveBuyer');
        $method->setAccessible(true);

        $buyer = $method->invoke($service, $invoice, null);

        $this->assertSame('Ipaymu User', $buyer['name']);
        $this->assertSame('ipaymu@example.com', $buyer['email']);
        $this->assertSame('081000000000', $buyer['phone']);
    }
}
