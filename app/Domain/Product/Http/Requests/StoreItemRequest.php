<?php

namespace App\Domain\Product\Http\Requests;

use App\Domain\Product\Enums\ItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
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
            'sku' => ['required', 'string', 'max:64', 'alpha_dash'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit_price_cents' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(ItemStatus::values())],
        ];
    }
}
