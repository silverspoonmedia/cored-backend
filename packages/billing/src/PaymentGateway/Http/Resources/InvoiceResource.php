<?php

namespace App\Domain\PaymentGateway\Http\Resources;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannelRouteSnapshot;
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
     * @param  mixed  $resource
     */
    protected static function newCollection($resource): InvoiceResourceCollection
    {
        return new InvoiceResourceCollection($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->currency;
        $this->loadMissing('paymentChannelRouteSnapshot.paymentGateway');
        /** @var PaymentChannelRouteSnapshot|null $routeSnapshot */
        $routeSnapshot = $this->paymentChannelRouteSnapshot;
        $feeBasis = app(InvoiceChannelFeeService::class)->feeBasisFields($routeSnapshot);
        $staff = $this->viewerIsInvoiceStaff($request);

        return [
            'id' => $this->id,
            'invoiceable_id' => $this->invoiceable_id,
            'invoiceable_type' => $this->when($staff, $this->invoiceable_type),
            'invoiceable_status' => $this->resolveInvoiceableStatus(),
            'payment_gateway_id' => $this->when($staff, $this->payment_gateway_id),
            'payment_gateway' => $this->whenLoaded('paymentGateway', fn () => [
                'id' => $this->paymentGateway?->id,
                'name' => $this->paymentGateway?->name,
                'display_name' => $this->paymentGateway?->display_name,
            ]),
            'invoice_number' => $this->invoice_number,
            'external_transaction_id' => $this->when($staff, $this->external_transaction_id),
            'payment_method' => $this->payment_method,
            'payment_channel_code' => $this->payment_channel_code,
            'payment_channel_route_id' => $this->when($staff, $this->payment_channel_route_id),
            'payment_channel_route_snapshot_id' => $this->when($staff, $this->payment_channel_route_snapshot_id),
            'payment_channel_route' => $this->when(
                $staff && $this->relationLoaded('paymentChannelRouteSnapshot'),
                fn () => $routeSnapshot
                    ? (new PaymentChannelRouteSnapshotResource($routeSnapshot))->resolve()
                    : null,
            ),
            ...$this->moneyFields('subtotal', $this->subtotal, $currency),
            ...$this->moneyFields('payment_channel_fee', $this->payment_channel_fee, $currency),
            ...$feeBasis,
            ...$this->moneyFields('customer_gross_total', $this->customer_gross_total, $currency),
            'currency' => $currency,
            'status' => $this->status,
            ...$this->dateFields('paid_at', $this->paid_at),
            ...$this->dateFields('expired_at', $this->expired_at),
            ...($staff ? $this->dateFields('withdrawable_at', $this->withdrawable_at) : []),
            'disbursement_status' => $this->when($staff, $this->disbursement_status->value ?? $this->disbursement_status),
            ...($staff ? $this->dateFields('disbursed_at', $this->disbursed_at) : []),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
            'checkout_session' => app(InvoiceCheckoutInstructionResolver::class)->resolve($this->resource, $request),
        ];
    }

    private function viewerIsInvoiceStaff(Request $request): bool
    {
        return $request->user()?->can('view invoice') ?? false;
    }

    /**
     * Resolve the invoiceable's current status string (mirrors PaymentRealtime).
     *
     * Used by checkout JS to soft-update the purchase status badge on
     * sync-status polling without a full page reload.
     */
    private function resolveInvoiceableStatus(): ?string
    {
        $invoiceable = $this->whenLoaded('invoiceable', fn () => $this->invoiceable, $this->invoiceable);

        if ($invoiceable === null) {
            return null;
        }

        $status = $invoiceable->status ?? null;

        if ($status === null) {
            return null;
        }

        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        if (is_object($status) && property_exists($status, 'value')) {
            return (string) $status->value;
        }

        return (string) $status;
    }
}
