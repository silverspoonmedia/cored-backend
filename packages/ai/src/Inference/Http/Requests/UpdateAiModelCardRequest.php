<?php

namespace App\Domain\Inference\Http\Requests;

use App\Domain\Inference\Http\Requests\Concerns\InferenceCatalogRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAiModelCardRequest extends FormRequest
{
    use InferenceCatalogRules;

    public function authorize(): bool
    {
        return $this->canManageInferenceCatalog();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->aiModelCardRules(forUpdate: true, ignoreCard: $this->route('model_card'));
    }
}
