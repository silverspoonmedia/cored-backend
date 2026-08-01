<?php

namespace App\Domain\Inference\Http\Requests;

use App\Domain\Inference\Http\Requests\Concerns\AuthorizesInferenceCatalog;
use Illuminate\Foundation\Http\FormRequest;

class InferenceUsageOverviewRequest extends FormRequest
{
    use AuthorizesInferenceCatalog;

    public function authorize(): bool
    {
        return $this->canViewInferenceCatalog();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'model_slug' => ['nullable', 'string', 'max:128'],
            'model_card_id' => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'uuid'],
            'stage' => ['nullable', 'string', 'max:64'],
            'inference_provider_id' => ['nullable', 'uuid'],
        ];
    }
}
