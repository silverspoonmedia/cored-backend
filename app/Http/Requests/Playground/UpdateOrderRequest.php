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

            // Newly uploaded files (appended to whatever is kept below).
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx'],

            // Disk paths of existing attachments to keep. Omit the key entirely
            // to keep all current files (metadata-only edit); send an empty
            // array to drop them all.
            'keep_attachments' => ['sometimes', 'nullable', 'array'],
            'keep_attachments.*' => ['string'],

            // Marker for clients that manage the attachment set over multipart
            // (where an empty `keep_attachments` array cannot be encoded). When
            // truthy and `keep_attachments` is absent, the service treats it as
            // "keep none" instead of "keep all". A plain scalar, so it survives
            // multipart/form-data. See OrderService::update().
            'attachments_managed' => ['sometimes', 'boolean'],
        ];
    }
}
