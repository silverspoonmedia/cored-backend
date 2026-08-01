<?php

namespace App\Domain\Inference\Http\Requests;

use App\Domain\Inference\Http\Requests\Concerns\AuthorizesInferenceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiModelCardRequest extends FormRequest
{
    use AuthorizesInferenceCatalog;

    public function authorize(): bool
    {
        return $this->canManageInferenceCatalog();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:128', Rule::unique('ai_model_cards', 'slug')],
            'display_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
