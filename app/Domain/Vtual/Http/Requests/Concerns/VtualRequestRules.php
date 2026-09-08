<?php

namespace App\Domain\Vtual\Http\Requests\Concerns;

use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\ExternalLinkKind;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Support\Http\Requests\Concerns\ModalFieldRules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait VtualRequestRules
{
    use ModalFieldRules;

    protected function slugPattern(): string
    {
        return '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function externalLinkRules(bool $forUpdate): array
    {
        $sometimes = $forUpdate ? ['sometimes'] : [];

        $fields = [
            'label' => [null, ...$sometimes, 'nullable', 'string', 'max:120'],
            'url' => ['string', 'max:2048'],
            'kind' => [null, ...$sometimes, 'nullable', 'string', Rule::in(ExternalLinkKind::values())],
            'sort_order' => [null, ...$sometimes, 'nullable', 'integer', 'min:0', 'max:9999'],
        ];

        return $forUpdate ? $this->updateRules($fields) : $this->storeRules($fields);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function creatorRules(bool $forUpdate): array
    {
        $fields = [
            'display_name' => ['string', 'max:120'],
            'slug' => [null, ...($forUpdate
                ? ['sometimes', 'string', 'max:120', 'regex:'.$this->slugPattern()]
                : ['nullable', 'string', 'max:120', 'regex:'.$this->slugPattern()])],
            'bio' => [null, 'nullable', 'string', 'max:5000'],
            'avatar_url' => [null, 'nullable', 'string', 'max:2048', 'url'],
        ];

        if ($forUpdate) {
            $fields['status'] = [null, 'sometimes', 'string', Rule::in(EntityStatus::values())];
        }

        return $forUpdate ? $this->updateRules($fields) : $this->storeRules($fields);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function managementRules(bool $forUpdate): array
    {
        $fields = [
            'name' => ['string', 'max:120'],
            'slug' => [null, ...($forUpdate
                ? ['sometimes', 'string', 'max:120', 'regex:'.$this->slugPattern()]
                : ['nullable', 'string', 'max:120', 'regex:'.$this->slugPattern()])],
            'description' => [null, 'nullable', 'string', 'max:5000'],
            'logo_url' => [null, 'nullable', 'string', 'max:2048', 'url'],
        ];

        if ($forUpdate) {
            $fields['status'] = [null, 'sometimes', 'string', Rule::in(EntityStatus::values())];
        }

        return $forUpdate ? $this->updateRules($fields) : $this->storeRules($fields);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function platformCredentialRules(bool $forUpdate): array
    {
        $fields = [
            'label' => ['string', 'max:120'],
            'priority' => [null, ...($forUpdate
                ? ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999']
                : ['nullable', 'integer', 'min:0', 'max:999999'])],
            'secret' => [null, ...($forUpdate ? ['sometimes', 'array'] : ['required', 'array'])],
            ...$this->credentialSecretFieldRules(),
        ];

        if (! $forUpdate) {
            $fields['type'] = ['string', Rule::in(PlatformCredentialType::values())];
        }

        return $forUpdate ? $this->updateRules($fields) : $this->storeRules($fields);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function credentialSecretFieldRules(): array
    {
        return [
            'secret.api_key' => [null, 'nullable', 'string', 'max:4096'],
            'secret.client_id' => [null, 'nullable', 'string', 'max:4096'],
            'secret.client_secret' => [null, 'nullable', 'string', 'max:4096'],
            'secret.access_token' => [null, 'nullable', 'string', 'max:8192'],
            'secret.refresh_token' => [null, 'nullable', 'string', 'max:8192'],
            'secret.expires_at' => [null, 'nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @param  array<string, mixed>  $secret
     */
    protected function assertCredentialSecretPayload(Validator $validator, PlatformCredentialType $type, array $secret): void
    {
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
    }

    /**
     * @return list<mixed>
     */
    protected function inviteEmailRules(): array
    {
        return ['required', 'string', 'email:rfc', 'max:255'];
    }
}
