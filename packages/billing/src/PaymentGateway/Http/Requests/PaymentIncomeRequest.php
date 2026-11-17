<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view invoice') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'basis' => ['sometimes', 'nullable', 'string', Rule::in(['paid_at', 'withdrawable_at'])],
            'gateway' => ['sometimes', 'nullable', 'string', Rule::in(['midtrans', 'ipaymu'])],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only(['from', 'to', 'basis', 'gateway']);
    }
}
