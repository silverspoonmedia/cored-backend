<?php

namespace App\Domain\Service\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutServiceRequest extends FormRequest
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
        $maxQuantity = max(1, (int) config('service.purchase.max_quantity', 99));

        return [
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.$maxQuantity],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('quantity')) {
            $this->merge(['quantity' => 1]);
        }
    }
}
