<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\ExternalLinkKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExternalLinkRequest extends FormRequest
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
            'label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'url' => ['sometimes', 'string', 'max:2048'],
            'kind' => ['sometimes', 'nullable', 'string', Rule::in(ExternalLinkKind::values())],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
