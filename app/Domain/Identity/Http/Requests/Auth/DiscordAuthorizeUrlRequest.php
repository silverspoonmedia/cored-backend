<?php

namespace App\Domain\Identity\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscordAuthorizeUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'intent' => ['required', 'string', Rule::in([
                'profile',
                'claim_verify',
                'claim_channel',
                'channel_claim_verify',
            ])],
            'return_path' => ['required', 'string', 'max:512'],
            'slug' => ['required_if:intent,claim_verify,claim_channel,channel_claim_verify', 'nullable', 'string', 'max:128'],
            'claim_id' => ['required_if:intent,claim_verify,channel_claim_verify', 'nullable', 'uuid'],
            'channel_id' => ['required_if:intent,claim_channel', 'nullable', 'uuid'],
        ];
    }
}
