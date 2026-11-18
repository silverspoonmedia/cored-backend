<?php

namespace App\Domain\PaymentGateway\Http\Requests\Payments\Midtrans;

// Internal
use Illuminate\Foundation\Http\FormRequest;

// Class
class MidtransChargeRequest extends FormRequest
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
            'charge' => ['required', 'array'],
            'charge.payment_type' => ['required', 'string'],
        ];
    }
}
