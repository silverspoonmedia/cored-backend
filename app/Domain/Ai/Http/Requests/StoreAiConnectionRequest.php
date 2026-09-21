<?php

namespace App\Domain\Ai\Http\Requests;

use App\Domain\Ai\Enums\AiConnectionDriver;
use App\Domain\Ai\Support\AiConnectionSecrets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiConnectionRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'label' => ['required', 'string', 'max:128'],
            'driver' => ['sometimes', 'string', Rule::in(AiConnectionDriver::values())],
            'endpoint' => ['required', 'string', 'max:255', 'url'],
            'test_model' => ['required', 'string', 'max:128'],
            'timeout_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3600'],
            'connect_timeout_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'max_retries' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'extra_headers' => ['sometimes', 'nullable', 'array'],
            'extra_headers.*' => ['nullable', 'string', 'max:1024'],
            'is_default' => ['sometimes', 'boolean'],
            'secret' => ['required', 'array'],
            'secret.api_key' => ['required', 'string', 'max:4096'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $secret = $this->input('secret');

        // Accept a flat api_key field as a convenience for the SPA form.
        if (! is_array($secret) && is_string($this->input('api_key'))) {
            $this->merge(['secret' => ['api_key' => $this->input('api_key')]]);
        }
    }

    public function connectionPayload(): array
    {
        $data = $this->validated();
        $data['secret'] = AiConnectionSecrets::normalizePayload((array) $data['secret']);

        return $data;
    }
}
