<?php

namespace App\Domain\FileHosting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFolderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
        ];
    }
}
