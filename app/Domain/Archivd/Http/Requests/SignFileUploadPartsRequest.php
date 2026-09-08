<?php

namespace App\Domain\Archivd\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignFileUploadPartsRequest extends FormRequest
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
            'part_numbers' => ['required', 'array', 'min:1', 'max:100'],
            'part_numbers.*' => ['integer', 'min:1'],
        ];
    }
}
