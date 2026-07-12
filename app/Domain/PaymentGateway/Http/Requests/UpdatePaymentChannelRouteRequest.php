<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentChannelRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage payment gateway') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
