<?php

namespace Tests\Unit\Http\Resources;

use App\Domain\FileHosting\Http\Resources\HostedFileResource;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\PaymentGateway\Http\Resources\InvoiceResource;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Service\Http\Resources\ServicePurchaseResource;
use App\Domain\Service\Models\ServicePurchase;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Http\Resources\ProductActivationResource;
use App\Support\Product\Models\ProductActivation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PresentationResourceTest extends TestCase
{
    public function test_invoice_resource_emits_money_and_date_companions(): void
    {
        app()->setLocale('en');

        $invoice = Invoice::make([
            'id' => (string) Str::uuid(),
            'subtotal' => '10000.00',
            'payment_channel_fee' => '0.00',
            'customer_gross_total' => '10000.00',
            'currency' => 'IDR',
            'status' => 'pending',
        ]);
        $invoice->created_at = Carbon::parse('2026-07-23 12:00:00');
        $invoice->updated_at = Carbon::parse('2026-07-23 12:00:00');

        $payload = (new InvoiceResource($invoice))->toArray(Request::create('/'));

        $this->assertSame('10000.00', $payload['subtotal']);
        $this->assertSame('IDR 10,000.00', $payload['subtotal_formatted']);
        $this->assertSame('IDR 10,000.00', $payload['customer_gross_total_formatted']);
        $this->assertNotNull($payload['created_at']);
        $this->assertStringContainsString('2026-07-23', $payload['created_at']);
        $this->assertNotNull($payload['created_at_formatted']);
        $this->assertNotNull($payload['created_at_human']);
        $this->assertArrayHasKey('paid_at_formatted', $payload);
        $this->assertNull($payload['paid_at']);
        $this->assertNull($payload['paid_at_formatted']);
    }

    public function test_hosted_file_resource_emits_size_human(): void
    {
        $file = HostedFile::make([
            'id' => (string) Str::uuid(),
            'name' => 'demo.bin',
            'disk' => 's3',
            'path' => 'u/demo.bin',
            'size' => 1536,
            'mime' => 'application/octet-stream',
            'visibility' => 'private',
            'download_count' => 0,
            'view_count' => 0,
        ]);
        $file->created_at = Carbon::parse('2026-07-01 08:00:00');
        $file->updated_at = Carbon::parse('2026-07-01 08:00:00');

        $payload = (new HostedFileResource($file))->toArray(Request::create('/'));

        $this->assertSame(1536, $payload['size']);
        $this->assertSame('1.5 KB', $payload['size_human']);
        $this->assertNotNull($payload['created_at_formatted']);
    }

    public function test_service_purchase_resource_money_uses_display_and_settlement_currency(): void
    {
        app()->setLocale('en');

        $purchase = ServicePurchase::make([
            'id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'service_id' => (string) Str::uuid(),
            'display_currency' => 'USD',
            'display_amount' => '12.50',
            'list_amount' => '15.00',
            'credit_amount' => '2.50',
            'charge_amount' => '12.50',
            'surplus_days' => 0,
            'settlement_currency' => 'IDR',
            'settlement_amount' => '200000.00',
            'status' => 'pending',
        ]);
        $purchase->created_at = now();
        $purchase->updated_at = now();

        $payload = (new ServicePurchaseResource($purchase))->toArray(Request::create('/'));

        $this->assertSame(12.5, $payload['display_amount']);
        $this->assertSame('USD 12.50', $payload['display_amount_formatted']);
        $this->assertSame('USD 15.00', $payload['list_amount_formatted']);
        $this->assertSame('USD 2.50', $payload['credit_amount_formatted']);
        $this->assertSame('USD 12.50', $payload['charge_amount_formatted']);
        $this->assertSame(200000.0, $payload['settlement_amount']);
        $this->assertSame('IDR 200,000.00', $payload['settlement_amount_formatted']);
    }

    public function test_product_activation_resource_date_companions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 20:00:00'));
        app()->setLocale('en');

        $activation = ProductActivation::make([
            'id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'product_key' => 'file-hosting',
            'state' => ProductActivationState::Active,
            'source' => 'test',
        ]);
        $activation->activated_at = Carbon::parse('2026-07-23 18:00:00');
        $activation->updated_at = Carbon::parse('2026-07-23 19:00:00');
        $activation->created_at = Carbon::parse('2026-07-23 17:00:00');

        $payload = (new ProductActivationResource($activation))->toArray(Request::create('/'));

        $this->assertNotNull($payload['activated_at']);
        $this->assertNotNull($payload['activated_at_formatted']);
        $this->assertNotNull($payload['activated_at_human']);
        $this->assertNotNull($payload['updated_at_human']);

        Carbon::setTestNow();
    }

    public function test_money_locale_id_differs_from_en(): void
    {
        $invoice = Invoice::make([
            'id' => (string) Str::uuid(),
            'subtotal' => '10000.50',
            'payment_channel_fee' => '0',
            'customer_gross_total' => '10000.50',
            'currency' => 'IDR',
            'status' => 'pending',
        ]);
        $invoice->created_at = now();
        $invoice->updated_at = now();

        app()->setLocale('en');
        $en = (new InvoiceResource($invoice))->toArray(Request::create('/'));

        app()->setLocale('id');
        $id = (new InvoiceResource($invoice))->toArray(Request::create('/'));

        $this->assertSame('IDR 10,000.50', $en['subtotal_formatted']);
        $this->assertNotSame($en['subtotal_formatted'], $id['subtotal_formatted']);
        $this->assertStringStartsWith('IDR ', $id['subtotal_formatted']);
    }
}
