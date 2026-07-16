<?php

namespace App\Domain\FileHosting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitFileUploadRequest extends FormRequest
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
        $uploadable = \App\Domain\FileHosting\Support\RegionResolver::uploadableKeys();

        return [
            'name' => ['required', 'string', 'max:255'],
            'mime' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'folder_id' => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'uuid'],
            'disk' => $uploadable === []
                ? ['nullable', 'string']
                : ['nullable', 'string', 'in:'.implode(',', $uploadable)],
        ];
    }
}
