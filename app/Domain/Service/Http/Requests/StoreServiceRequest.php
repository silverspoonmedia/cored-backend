<?php

namespace App\Domain\Service\Http\Requests;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'billing_interval' => ['required', 'string', Rule::in(BillingInterval::values())],
            'fulfillment_target' => ['nullable', 'string', 'max:64'],
            'entitlement' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'string', Rule::in(ServiceStatus::values())],
            'prices' => ['sometimes', 'array'],
            'prices.*.currency' => [
                'required_with:prices',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'prices.*.amount' => ['required_with:prices', 'numeric', 'min:0'],
        ];
    }
}
