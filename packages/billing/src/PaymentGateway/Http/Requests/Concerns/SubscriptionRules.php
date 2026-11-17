<?php

namespace App\Domain\PaymentGateway\Http\Requests\Concerns;

use App\Domain\PaymentGateway\Enums\BillingCycle;
use App\Domain\PaymentGateway\Enums\SubscriptionStatus;
use Illuminate\Validation\Rule;

trait SubscriptionRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function subscriptionRules(bool $forUpdate): array
    {
        if ($forUpdate) {
            return [
                'subscriber_name' => ['sometimes', 'string', 'max:255'],
                'subscriber_email' => ['sometimes', 'string', 'email', 'max:255'],
                'subscriber_phone' => ['nullable', 'string', 'max:32'],
                'plan_name' => ['sometimes', 'string', 'max:255'],
                'plan_description' => ['nullable', 'string'],
                'billing_cycle' => ['sometimes', Rule::enum(BillingCycle::class)],
                'amount' => ['sometimes', 'numeric', 'min:0'],
                'currency' => ['sometimes', 'string', 'size:3'],
                'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
                'trial_ends_at' => ['nullable', 'date'],
                'started_at' => ['nullable', 'date'],
                'expires_at' => ['nullable', 'date'],
                'auto_renew' => ['sometimes', 'boolean'],
                'cancelled_at' => ['nullable', 'date'],
                'cancellation_reason' => ['nullable', 'string'],
                'metadata' => ['sometimes', 'array'],
            ];
        }

        return [
            'subscriber_name' => ['required', 'string', 'max:255'],
            'subscriber_email' => ['required', 'string', 'email', 'max:255'],
            'subscriber_phone' => ['nullable', 'string', 'max:32'],
            'plan_name' => ['required', 'string', 'max:255'],
            'plan_description' => ['nullable', 'string'],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            'trial_ends_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'auto_renew' => ['sometimes', 'boolean'],
            'payment_gateway_id' => ['sometimes', 'uuid', 'exists:payment_gateways,id'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
