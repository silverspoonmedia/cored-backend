<?php

namespace App\Domain\Playground\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Input (HTTP-shape) validation for the mass-delete action. Only checks the
 * shape of the id set; the "completed orders are frozen" rule stays in
 * OrderService, exactly like the single-row delete.
 */
class BulkDestroyOrderRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid', 'distinct', 'exists:order_master,id'],
        ];
    }
}
