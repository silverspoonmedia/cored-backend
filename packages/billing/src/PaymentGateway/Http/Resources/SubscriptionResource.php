<?php

namespace App\Domain\PaymentGateway\Http\Resources;

use App\Domain\PaymentGateway\Models\Subscription;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
class SubscriptionResource extends JsonResource
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
            'subscription_number' => $this->subscription_number,
            'subscriber_name' => $this->subscriber_name,
            'subscriber_email' => $this->subscriber_email,
            'subscriber_phone' => $this->subscriber_phone,
            'plan_name' => $this->plan_name,
            'plan_description' => $this->plan_description,
            'billing_cycle' => $this->billing_cycle->value ?? $this->billing_cycle,
            ...$this->moneyFields('amount', $this->amount, $currency),
            'currency' => $currency,
            'status' => $this->status->value ?? $this->status,
            ...$this->dateFields('trial_ends_at', $this->trial_ends_at),
            ...$this->dateFields('started_at', $this->started_at),
            ...$this->dateFields('expires_at', $this->expires_at),
            'auto_renew' => $this->auto_renew,
            ...$this->dateFields('cancelled_at', $this->cancelled_at),
            'invoice' => $this->whenLoaded('invoice', fn () => new InvoiceResource($this->invoice)),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
