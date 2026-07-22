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
            'external_id' => ['required', 'string', 'max:191'],
            'handle' => ['nullable', 'string', 'max:191'],
            'display_name' => ['nullable', 'string', 'max:191'],
            'url' => ['nullable', 'string', 'max:2048', 'url'],
        ];
    }
}
