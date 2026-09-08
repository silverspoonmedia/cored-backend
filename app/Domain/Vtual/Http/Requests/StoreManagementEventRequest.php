<?php

namespace App\Domain\Vtual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagementEventRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'thumbnail_url' => ['nullable', 'string', 'url', 'max:2048'],
            'url' => ['nullable', 'string', 'url', 'max:2048'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['nullable', 'string', 'timezone', 'max:64'],
            'creator_id' => ['nullable', 'uuid', Rule::exists('vtual_creators', 'id')->whereNull('deleted_at')],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }
}
