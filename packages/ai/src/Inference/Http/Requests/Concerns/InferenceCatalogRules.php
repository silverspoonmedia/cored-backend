<?php

namespace App\Domain\Inference\Http\Requests\Concerns;

use App\Support\Http\Requests\Concerns\ModalFieldRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait InferenceCatalogRules
{
    use AuthorizesInferenceCatalog;
    use ModalFieldRules;

    /**
     * @return array<string, list<mixed>>
     */
    protected function inferenceProviderRules(bool $forUpdate, Model|int|string|null $ignoreProvider = null): array
    {
        $fields = [
            'slug' => ['string', 'max:64', $this->uniqueSlug('ai_inference_providers', $ignoreProvider)],
            'name' => ['string', 'max:128'],
            'is_active' => [null, 'sometimes', 'boolean'],
        ];

        return $forUpdate ? $this->updateRules($fields) : $this->storeRules($fields);
    }

    /**
     * Store {@code display_name} stays nullable-only; update is {@code sometimes|nullable}.
     *
     * @return array<string, list<mixed>>
     */
    protected function aiModelCardRules(bool $forUpdate, Model|int|string|null $ignoreCard = null): array
    {
        $fields = [
            'slug' => ['string', 'max:128', $this->uniqueSlug('ai_model_cards', $ignoreCard)],
            'display_name' => [
                null,
                ...($forUpdate
                    ? ['sometimes', 'nullable', 'string', 'max:255']
                    : ['nullable', 'string', 'max:255']),
            ],
            'is_active' => [null, 'sometimes', 'boolean'],
        ];

        return $forUpdate ? $this->updateRules($fields) : $this->storeRules($fields);
    }

    protected function uniqueSlug(string $table, Model|int|string|null $ignore = null): Unique
    {
        $rule = Rule::unique($table, 'slug');

        return $ignore !== null ? $rule->ignore($ignore) : $rule;
    }
}
