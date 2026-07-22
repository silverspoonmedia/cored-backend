<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\PlatformKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChannelRequest extends FormRequest
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
            'platform_key' => ['required', 'string', Rule::in(PlatformKey::values())],
            'profile' => ['required', 'string', 'max:512'],
        ];
    }
}
