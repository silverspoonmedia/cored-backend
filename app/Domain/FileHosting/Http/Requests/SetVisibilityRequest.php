<?php

namespace App\Domain\FileHosting\Http\Requests;

use App\Domain\FileHosting\Enums\Visibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetVisibilityRequest extends FormRequest
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
            'visibility' => ['required', 'string', Rule::in(Visibility::values())],
            'user_id' => ['nullable', 'uuid'],
        ];
    }
}
