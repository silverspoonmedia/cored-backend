<?php

namespace App\Domain\FileHosting\Http\Requests;

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
            'part_numbers' => ['required', 'array', 'min:1'],
            'part_numbers.*' => ['integer', 'min:1'],
        ];
    }
}
