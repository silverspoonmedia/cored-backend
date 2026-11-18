<?php

namespace App\Domain\PaymentGateway\Http\Requests\Payments\Midtrans;

// Internal
use Illuminate\Foundation\Http\FormRequest;

// Class
class MidtransRefundRequest extends FormRequest
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
            'amount' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
