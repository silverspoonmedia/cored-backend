<?php

namespace App\Domain\PaymentGateway\Http\Resources;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Support\InvoiceWebDestination;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class PaymentIncomeInvoiceResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->currency;

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'payment_channel_code' => $this->payment_channel_code,
            'payment_gateway' => $this->whenLoaded('paymentGateway', fn () => [
                'name' => $this->paymentGateway?->name,
                'display_name' => $this->paymentGateway?->display_name,
            ]),
            'invoiceable_type' => $this->invoiceable_type,
            ...$this->moneyFields('subtotal', $this->subtotal, $currency),
            ...$this->moneyFields('payment_channel_fee', $this->payment_channel_fee, $currency),
            ...$this->moneyFields('customer_gross_total', $this->customer_gross_total, $currency),
            ...$this->dateFields('paid_at', $this->paid_at),
            ...$this->dateFields('withdrawable_at', $this->withdrawable_at),
            'disbursement_status' => $this->disbursement_status?->value ?? $this->disbursement_status,
            'show_url' => InvoiceWebDestination::showUrl($this->resource),
        ];
    }
}
