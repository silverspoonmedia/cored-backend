<?php

namespace App\Domain\Vtual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreatorInviteRequest extends FormRequest
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
        // Ownership (mode=existing) and linkage checks are enforced by the
        // service; here we only validate shape.
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'mode' => ['required', 'string', Rule::in(['existing', 'new'])],
            'creator_id' => ['nullable', 'required_if:mode,existing', 'string', 'max:64'],
            'new_display_name' => ['nullable', 'required_if:mode,new', 'string', 'max:120'],
        ];
    }
}
