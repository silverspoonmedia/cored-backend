<?php

namespace App\Domain\PaymentGateway\Http\Resources;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\InvoiceCheckoutInstructionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceable_id' => $this->invoiceable_id,
            'invoiceable_type' => $this->invoiceable_type,
            'payment_gateway_id' => $this->payment_gateway_id,
            'payment_gateway' => $this->whenLoaded('paymentGateway', fn () => [
                'id' => $this->paymentGateway?->id,
                'name' => $this->paymentGateway?->name,
                'display_name' => $this->paymentGateway?->display_name,
            ]),
            'invoice_number' => $this->invoice_number,
            'external_transaction_id' => $this->external_transaction_id,
            'payment_method' => $this->payment_method,
            'payment_channel_code' => $this->payment_channel_code,
            'subtotal' => $this->subtotal,
            'payment_channel_fee' => $this->payment_channel_fee,
            'customer_gross_total' => $this->customer_gross_total,
            'currency' => $this->currency,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'expired_at' => $this->expired_at?->toIso8601String(),
            'withdrawable_at' => $this->withdrawable_at?->toIso8601String(),
            'disbursement_status' => $this->disbursement_status?->value ?? $this->disbursement_status,
            'disbursed_at' => $this->disbursed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'checkout_session' => app(InvoiceCheckoutInstructionResolver::class)->resolve($this->resource),
        ];
    }
}
