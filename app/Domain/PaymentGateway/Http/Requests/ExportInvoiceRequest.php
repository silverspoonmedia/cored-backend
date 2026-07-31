<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportInvoiceRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', 'string', Rule::in([
                'pending', 'challenge', 'paid', 'expired', 'cancelled', 'failed', 'partially_refunded',
            ])],
            'paid_from' => ['sometimes', 'nullable', 'date'],
            'paid_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:paid_from'],
        ];
    }
}
