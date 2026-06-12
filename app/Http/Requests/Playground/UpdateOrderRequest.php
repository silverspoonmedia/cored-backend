<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update replaces the whole order (master + detail set), so the detail array is
 * still required: editing an order to have no lines is not a valid state.
 */
class UpdateOrderRequest extends FormRequest
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
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['pending', 'processing', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string'],

            'details' => ['required', 'array', 'min:1'],
            'details.*.id' => ['sometimes', 'nullable', 'uuid'],
            'details.*.product_name' => ['required', 'string', 'max:255'],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.unit_price' => ['required', 'numeric', 'min:0'],
            'details.*.discount' => ['nullable', 'numeric', 'min:0'],
            'details.*.notes' => ['nullable', 'string'],
        ];
    }
}
