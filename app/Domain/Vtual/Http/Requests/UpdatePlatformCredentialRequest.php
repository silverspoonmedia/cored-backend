<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Http\Requests\Concerns\VtualRequestRules;
use App\Domain\Vtual\Models\PlatformCredential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePlatformCredentialRequest extends FormRequest
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
        return $this->platformCredentialRules(forUpdate: true);
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

            $this->assertCredentialSecretPayload($validator, $type, $secret);
        });
    }
}
