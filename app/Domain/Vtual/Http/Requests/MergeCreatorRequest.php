<?php

namespace App\Domain\Vtual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeCreatorRequest extends FormRequest
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
            'destination_id' => ['required', 'uuid', 'exists:vtual_creators,id'],
        ];
    }
}
