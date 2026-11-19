<?php

namespace App\Domain\Ai\Http\Requests;

use App\Domain\Ai\Enums\AiConnectionDriver;
use App\Domain\Ai\Enums\AiConnectionStatus;
use App\Domain\Ai\Support\AiConnectionSecrets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every field is optional — PATCH semantics. An omitted `secret` keeps the
     * stored key (the SPA leaves the field blank to mean "unchanged").
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'label' => ['sometimes', 'nullable', 'string', 'max:128'],
            'driver' => ['sometimes', 'nullable', 'string', Rule::in(AiConnectionDriver::values())],
            'endpoint' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'test_model' => ['sometimes', 'nullable', 'string', 'max:128'],
            'timeout_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3600'],
            'connect_timeout_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'max_retries' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'extra_headers' => ['sometimes', 'nullable', 'array'],
            'extra_headers.*' => ['nullable', 'string', 'max:1024'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(AiConnectionStatus::values())],
            'secret' => ['sometimes', 'nullable', 'array'],
            'secret.api_key' => ['sometimes', 'nullable', 'string', 'max:4096'],
        ];
    }

    /**
     * Blank fields mean "keep the stored value" — Laravel's
     * ConvertEmptyStringsToNull turns them into null before validation, so a
     * null here is intentional, not malformed input.
     */
    protected function prepareForValidation(): void
    {
        $secret = $this->input('secret');

        if (! is_array($secret) && is_string($this->input('api_key'))) {
            $this->merge(['secret' => ['api_key' => $this->input('api_key')]]);
        }
    }

    public function connectionPayload(): array
    {
        // Blank text fields (slug/label/endpoint/test_model) mean "keep the
        // stored value". The numeric + header overrides are the exception: an
        // explicit null there clears the override so the connection falls back
        // to its own default, so they survive the null filter.
        $clearable = ['timeout_seconds', 'connect_timeout_seconds', 'max_retries', 'extra_headers'];

        $data = array_filter(
            $this->validated(),
            static fn (mixed $value, string $key): bool => $value !== null || in_array($key, $clearable, true),
            ARRAY_FILTER_USE_BOTH,
        );

        if (array_key_exists('secret', $data)) {
            /** @var array<string, mixed> $payload */
            $payload = (array) $data['secret'];
            $payload = array_filter($payload, static fn (mixed $value): bool => $value !== null);

            // Blank key = keep the stored secret.
            if (trim((string) ($payload['api_key'] ?? '')) === '') {
                unset($data['secret']);

                return $data;
            }

            $data['secret'] = AiConnectionSecrets::normalizePayload($payload);
        }

        return $data;
    }
}
