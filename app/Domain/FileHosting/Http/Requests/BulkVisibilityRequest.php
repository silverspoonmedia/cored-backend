<?php

namespace App\Domain\FileHosting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkVisibilityRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid'],
            'visibility' => ['required', 'string', 'in:private,anyone_with_link'],
            'user_id' => ['nullable', 'uuid'],
        ];
    }
}
