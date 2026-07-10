<?php

namespace App\Domain\Playground\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignAttachmentUploadPartsRequest extends FormRequest
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
            'part_numbers' => ['required', 'array', 'min:1'],
            'part_numbers.*' => ['required', 'integer', 'min:1'],
        ];
    }
}
