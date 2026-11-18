<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use App\Domain\PaymentGateway\Http\Requests\Concerns\PaymentGatewayRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentGatewayRequest extends FormRequest
{
    use PaymentGatewayRules;

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

        return $this->gatewayRules(forUpdate: true, ignoreId: $id);
    }
}
