<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Models\PlatformCredential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePlatformCredentialRequest extends FormRequest
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
            'label' => ['sometimes', 'string', 'max:120'],
            'priority' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999'],
            // Promoting to active is blocked in service unless already active (keep).
            'status' => ['sometimes', 'string', Rule::in(PlatformCredentialStatus::values())],
            'secret' => ['sometimes', 'array'],
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
            if (! $this->has('secret')) {
                return;
            }

            $secret = $this->input('secret');
            if (! is_array($secret) || $secret === []) {
                // Empty secret object means "leave unchanged" — treat as omit.
                return;
            }

            /** @var PlatformCredential|null $credential */
            $credential = $this->route('credential');
            if (! $credential instanceof PlatformCredential) {
                return;
            }

            $type = $credential->type instanceof PlatformCredentialType
                ? $credential->type
                : PlatformCredentialType::from((string) $credential->type);

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
