<?php

namespace App\Domain\Service\Http\Requests;

use App\Domain\Service\Http\Requests\Concerns\ServiceCatalogRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    use ServiceCatalogRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCatalogForValidation();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->catalogRules(forUpdate: true);
    }
}
