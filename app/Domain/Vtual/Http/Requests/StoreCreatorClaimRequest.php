<?php

namespace App\Domain\Vtual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCreatorClaimRequest extends FormRequest
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
            'creator_id' => ['required', 'uuid'],
            'channel_id' => ['required', 'uuid'],
        ];
    }
}
