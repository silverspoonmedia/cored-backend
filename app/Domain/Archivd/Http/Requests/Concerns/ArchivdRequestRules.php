<?php

namespace App\Domain\Archivd\Http\Requests\Concerns;

use App\Domain\Archivd\Enums\Visibility;
use Illuminate\Validation\Rule;

trait ArchivdRequestRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function renameRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function moveTargetRules(string $targetField): array
    {
        return [
            $targetField => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function bulkIdsRules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid'],
            'user_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function visibilityRules(bool $required = true): array
    {
        $mod = $required ? 'required' : 'sometimes';

        return [
            'visibility' => [$mod, 'string', Rule::in(Visibility::values())],
        ];
    }
}
