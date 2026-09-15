<?php

namespace App\Domain\Microstock\Http\Requests\Concerns;

trait MicrostockRequestRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function collectionLockRules(): array
    {
        return [
            'hint' => ['nullable', 'string', 'max:2000'],
            'billing_mode' => ['nullable', 'string', 'in:free,paid'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function metadataFinalizeRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'keywords' => ['required', 'array'],
            'keywords.*' => ['string', 'max:100'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function storeWorkspaceRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function storeCredentialRules(): array
    {
        return [
            'marketplace' => ['required', 'string', 'max:100'],
            'label' => ['required', 'string', 'max:100'],
            'secret' => ['required', 'array'],
        ];
    }
}
