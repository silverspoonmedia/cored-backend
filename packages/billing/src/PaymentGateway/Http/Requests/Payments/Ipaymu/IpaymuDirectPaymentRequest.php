<?php

namespace App\Domain\PaymentGateway\Http\Requests\Payments\Ipaymu;

// Internal
use Illuminate\Foundation\Http\FormRequest;

// Class
class IpaymuDirectPaymentRequest extends FormRequest
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
            'payment_method' => ['required', 'string', 'max:32'],
            'payment_channel' => ['required', 'string', 'max:32'],
            'buyer' => ['sometimes', 'array'],
            'buyer.name' => ['required_with:buyer', 'string', 'max:255'],
            'buyer.email' => ['required_with:buyer', 'string', 'email', 'max:255'],
            'buyer.phone' => ['required_with:buyer', 'string', 'max:32'],
            'expired' => ['sometimes', 'string', 'max:16'],
            'expired_type' => ['sometimes', 'string', 'in:days,hours,minutes,seconds'],
        ];
    }
}
