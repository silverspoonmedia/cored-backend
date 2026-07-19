<?php

namespace App\Domain\FileHosting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:1', 'max:255'],
            'user_id' => ['nullable', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
        ];
    }
}
