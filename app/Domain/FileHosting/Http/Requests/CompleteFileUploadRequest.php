<?php

namespace App\Domain\FileHosting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteFileUploadRequest extends FormRequest
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
            'parts' => ['nullable', 'array'],
            'parts.*.part_number' => ['required_with:parts', 'integer', 'min:1'],
            'parts.*.etag' => ['required_with:parts', 'string'],
            'parts.*.size' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
