<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Http\Requests\Concerns\VtualRequestRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePlatformCredentialRequest extends FormRequest
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
        return $this->platformCredentialRules(forUpdate: false);
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

            $this->assertCredentialSecretPayload($validator, $type, $secret);
        });
    }
}
