<?php

namespace App\Domain\FileHosting\Http\Requests;

use App\Domain\FileHosting\Http\Requests\Concerns\FileHostingRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class SetVisibilityRequest extends FormRequest
{
    use FileHostingRequestRules;

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
