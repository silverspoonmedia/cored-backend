<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use App\Domain\PaymentGateway\Http\Requests\Concerns\SubscriptionRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    use SubscriptionRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manage subscription') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->subscriptionRules(forUpdate: true);
    }
}
