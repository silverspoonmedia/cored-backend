<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\EntityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreatorRequest extends FormRequest
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
            'display_name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'avatar_url' => ['nullable', 'string', 'max:2048', 'url'],
            'status' => ['sometimes', 'string', Rule::in(EntityStatus::values())],
        ];
    }
}
