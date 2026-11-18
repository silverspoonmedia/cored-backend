<?php

namespace App\Domain\Inference\Http\Requests;

use App\Domain\Inference\Http\Requests\Concerns\AuthorizesInferenceCatalog;
use App\Http\Requests\Concerns\InteractsWithListDisplayMode;
use Illuminate\Foundation\Http\FormRequest;

class IndexInferenceProviderRequest extends FormRequest
{
    use AuthorizesInferenceCatalog;
    use InteractsWithListDisplayMode;

    public function authorize(): bool
    {
        return $this->canViewInferenceCatalog();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->listDisplayModeRules();
    }
}
