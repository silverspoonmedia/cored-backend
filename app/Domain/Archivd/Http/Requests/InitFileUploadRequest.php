<?php

namespace App\Domain\Archivd\Http\Requests;

use App\Domain\Archivd\Support\RegionResolver;
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
        $uploadable = RegionResolver::uploadableKeys();

        return [
            'name' => ['required', 'string', 'max:255'],
            'mime' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'folder_id' => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'disk' => $uploadable === []
                ? ['nullable', 'string']
                : ['nullable', 'string', 'in:'.implode(',', $uploadable)],
        ];
    }
}
