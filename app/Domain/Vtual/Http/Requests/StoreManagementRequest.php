<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Http\Requests\Concerns\VtualRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreManagementRequest extends FormRequest
{
    use VtualRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->managementRules(forUpdate: false);
    }
}
