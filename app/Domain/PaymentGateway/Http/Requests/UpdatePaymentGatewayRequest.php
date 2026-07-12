<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = (string) $this->route('payment_gateway');

        return [
            'name' => ['sometimes', 'string', 'max:64', Rule::unique('payment_gateways', 'name')->ignore($id)],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
