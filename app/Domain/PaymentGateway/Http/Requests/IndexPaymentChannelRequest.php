<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexPaymentChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->input('scope') === 'admin') {
            return $this->user()?->can('manage payment gateway') ?? false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', 'string', 'in:admin'],
        ];
    }
}
