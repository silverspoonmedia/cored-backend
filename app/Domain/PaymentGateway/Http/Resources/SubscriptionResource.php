<?php

namespace App\Domain\PaymentGateway\Http\Resources;

use App\Domain\PaymentGateway\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_number' => $this->subscription_number,
            'subscriber_name' => $this->subscriber_name,
            'subscriber_email' => $this->subscriber_email,
            'subscriber_phone' => $this->subscriber_phone,
            'plan_name' => $this->plan_name,
            'plan_description' => $this->plan_description,
            'billing_cycle' => $this->billing_cycle?->value ?? $this->billing_cycle,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status?->value ?? $this->status,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'auto_renew' => $this->auto_renew,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'invoice' => $this->whenLoaded('invoice', fn () => new InvoiceResource($this->invoice)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
