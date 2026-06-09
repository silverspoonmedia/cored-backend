<?php

namespace App\Http\Requests\Navigation;

use App\Http\Requests\Concerns\InteractsWithListDisplayMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMenuListRequest extends FormRequest
{
    use InteractsWithListDisplayMode;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->listDisplayModeRules(), [
            'load_type' => ['sometimes', 'nullable', 'string', Rule::in(['h', 'p', 'c'])],
        ]);
    }
}
