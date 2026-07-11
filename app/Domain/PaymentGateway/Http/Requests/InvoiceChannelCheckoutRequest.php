<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceChannelCheckoutRequest extends FormRequest
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
        return [
            'channel_code' => ['required', 'string', 'max:64', 'exists:payment_channels,code'],
            'buyer' => ['sometimes', 'array'],
            'buyer.name' => ['required_with:buyer', 'string', 'max:255'],
            'buyer.email' => ['required_with:buyer', 'string', 'email', 'max:255'],
            'buyer.phone' => ['required_with:buyer', 'string', 'max:32'],
        ];
    }
}
