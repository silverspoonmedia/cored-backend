<?php

namespace App\Http\Requests\Playground;

use App\Rules\CompletedAttachmentUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Input (HTTP-shape) validation for creating a master-detail order. Business
 * rules — totals, "completed orders are frozen", "needs ≥1 line" — live in
 * OrderService, not here.
 */
class StoreOrderRequest extends FormRequest
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
        $maxFiles = (int) config('attachments.max_files_per_order');

        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['pending', 'processing', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string'],

            'details' => ['required', 'array', 'min:1'],
            'details.*.product_name' => ['required', 'string', 'max:255'],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.unit_price' => ['required', 'numeric', 'min:0'],
            'details.*.discount' => ['nullable', 'numeric', 'min:0'],
            'details.*.notes' => ['nullable', 'string'],

            'staged_attachments' => ['nullable', 'array', 'max:'.$maxFiles],
            'staged_attachments.*' => ['required', 'uuid', new CompletedAttachmentUpload],
        ];
    }
}
