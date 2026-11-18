<?php

namespace App\Domain\PaymentGateway\Http\Requests\Payments\Midtrans;

// Internal
use Illuminate\Foundation\Http\FormRequest;

// Class
class MidtransSnapTokenRequest extends FormRequest
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
            'enabled_payments' => ['sometimes', 'array'],
            'enabled_payments.*' => ['string'],
            'credit_card' => ['sometimes', 'array'],
            'customer_details' => ['sometimes', 'array'],
        ];
    }
}
