<?php

namespace App\Domain\Inference\Http\Requests;

use App\Domain\Inference\Http\Requests\Concerns\AuthorizesInferenceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInferenceProviderRequest extends FormRequest
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
        $provider = $this->route('provider');

        return [
            'slug' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('ai_inference_providers', 'slug')->ignore($provider),
            ],
            'name' => ['sometimes', 'string', 'max:128'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
