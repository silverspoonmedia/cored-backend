<?php

namespace App\Domain\Vtual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit an existing management-submitted event. Mirrors
 * {@see StoreManagementEventRequest} but every field is optional and the
 * owning management never moves — the route already pins it.
 */
class UpdateManagementEventRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'url', 'max:2048'],
            'url' => ['sometimes', 'nullable', 'string', 'url', 'max:2048'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone', 'max:64'],
            'creator_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('vtual_creators', 'id')->whereNull('deleted_at')],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
