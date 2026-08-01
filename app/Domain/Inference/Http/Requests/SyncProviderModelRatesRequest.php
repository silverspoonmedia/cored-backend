<?php

namespace App\Domain\Inference\Http\Requests;

use App\Domain\Inference\Http\Requests\Concerns\AuthorizesInferenceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncProviderModelRatesRequest extends FormRequest
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
            'rates' => ['required', 'array'],
            'rates.*.model_card_id' => ['required', 'uuid', Rule::exists('ai_model_cards', 'id')],
            'rates.*.input_per_million_usd' => ['required', 'numeric', 'min:0', 'max:9999.9999'],
            'rates.*.output_per_million_usd' => ['required', 'numeric', 'min:0', 'max:9999.9999'],
        ];
    }
}
