<?php

namespace App\Domain\Archivd\Http\Requests;

use App\Domain\Archivd\Http\Requests\Concerns\ArchivdRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class SetVisibilityRequest extends FormRequest
{
    use ArchivdRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->visibilityRules(), [
            'user_id' => ['nullable', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
        ]);
    }
}
