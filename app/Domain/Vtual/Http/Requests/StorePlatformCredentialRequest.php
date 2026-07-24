<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePlatformCredentialRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in(PlatformCredentialType::values())],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999999'],
            // Ignored by service (always pending until Test); kept for backward-compatible clients.
            'status' => ['nullable', 'string', Rule::in(PlatformCredentialStatus::values())],
            'secret' => ['required', 'array'],
            'secret.api_key' => ['nullable', 'string', 'max:4096'],
            'secret.client_id' => ['nullable', 'string', 'max:4096'],
            'secret.client_secret' => ['nullable', 'string', 'max:4096'],
            'secret.access_token' => ['nullable', 'string', 'max:8192'],
            'secret.refresh_token' => ['nullable', 'string', 'max:8192'],
            'secret.expires_at' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $typeValue = $this->input('type');
            if (! is_string($typeValue) || $typeValue === '') {
                return;
            }

            try {
                $type = PlatformCredentialType::from($typeValue);
            } catch (\ValueError) {
                return;
            }

            $secret = $this->input('secret');
            if (! is_array($secret)) {
                return;
            }

            $allowed = array_merge($type->requiredPayloadKeys(), $type->optionalPayloadKeys());
            foreach (array_keys($secret) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add('secret.'.$key, __('api/vtual.credential.payload_unknown_key', ['field' => $key]));
                }
            }

            foreach ($type->requiredPayloadKeys() as $required) {
                $value = $secret[$required] ?? null;
                if (! is_string($value) && ! is_numeric($value)) {
                    $validator->errors()->add('secret.'.$required, __('api/vtual.credential.payload_missing', ['field' => $required]));

                    continue;
                }
                if (trim((string) $value) === '') {
                    $validator->errors()->add('secret.'.$required, __('api/vtual.credential.payload_missing', ['field' => $required]));
                }
            }
        });
    }
}
