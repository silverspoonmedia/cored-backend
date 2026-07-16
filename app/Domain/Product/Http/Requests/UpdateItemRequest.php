<?php

namespace App\Domain\Product\Http\Requests;

use App\Domain\Product\Http\Requests\Concerns\ItemRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    use ItemRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->itemRules(forUpdate: true);
    }
}
