<?php

namespace App\Domain\PaymentGateway\Http\Resources;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\Payment\InvoiceChannelFeeService;
use App\Domain\PaymentGateway\Services\Payment\InvoiceCheckoutInstructionResolver;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->currency;
        $this->loadMissing('paymentChannelRouteSnapshot.paymentGateway');
        $feeBasis = app(InvoiceChannelFeeService::class)->feeBasisFields($this->paymentChannelRouteSnapshot);
        $routeSnapshot = $this->paymentChannelRouteSnapshot;

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
            'payment_channel_route_id' => $this->payment_channel_route_id,
            'payment_channel_route_snapshot_id' => $this->payment_channel_route_snapshot_id,
            'payment_channel_route_snapshot' => $this->whenLoaded('paymentChannelRouteSnapshot', fn () => $routeSnapshot
                ? (new PaymentChannelRouteSnapshotResource($routeSnapshot))->resolve()
                : null),
            'payment_channel_route' => $this->whenLoaded('paymentChannelRouteSnapshot', fn () => $routeSnapshot
                ? (new PaymentChannelRouteSnapshotResource($routeSnapshot))->resolve()
                : null),
            ...$this->moneyFields('subtotal', $this->subtotal, $currency),
            ...$this->moneyFields('payment_channel_fee', $this->payment_channel_fee, $currency),
            ...$feeBasis,
            ...$this->moneyFields('customer_gross_total', $this->customer_gross_total, $currency),
            'currency' => $currency,
            'status' => $this->status,
            ...$this->dateFields('paid_at', $this->paid_at),
            ...$this->dateFields('expired_at', $this->expired_at),
            ...$this->dateFields('withdrawable_at', $this->withdrawable_at),
            'disbursement_status' => $this->disbursement_status?->value ?? $this->disbursement_status,
            ...$this->dateFields('disbursed_at', $this->disbursed_at),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
            'checkout_session' => app(InvoiceCheckoutInstructionResolver::class)->resolve($this->resource),
        ];
    }
}
