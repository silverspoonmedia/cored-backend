<?php

namespace App\Domain\PaymentGateway\Http\Requests\Payments\Ipaymu;

// Internal
use Illuminate\Foundation\Http\FormRequest;

// Class
class IpaymuSnapTokenRequest extends FormRequest
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
            'invoice_id' => ['required', 'uuid', 'exists:invoices,id'],
            'buyer' => ['sometimes', 'array'],
            'buyer.name' => ['required_with:buyer', 'string', 'max:255'],
            'buyer.email' => ['required_with:buyer', 'string', 'email', 'max:255'],
            'buyer.phone' => ['required_with:buyer', 'string', 'max:32'],
            'return_url' => ['sometimes', 'string', 'url', 'max:2048'],
            'cancel_url' => ['sometimes', 'string', 'url', 'max:2048'],
        ];
    }
}
