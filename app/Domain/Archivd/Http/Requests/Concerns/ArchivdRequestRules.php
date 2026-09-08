<?php

namespace App\Domain\Archivd\Http\Requests\Concerns;

use App\Domain\Archivd\Enums\Visibility;
use App\Domain\Archivd\Support\Sha256Checksum;
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

    /**
     * @return array<string, list<mixed>>
     */
    protected function storeWorkspaceRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function workspaceRoleRules(): array
    {
        return [
            'role' => ['required', 'string', 'in:admin,editor,viewer'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function inviteWorkspaceMemberRules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            ...$this->workspaceRoleRules(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function acceptWorkspaceInviteRules(): array
    {
        return [
            'token' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function sha256BlocklistRules(): array
    {
        return [
            'sha256' => ['required', 'string', 'size:64', 'regex:'.Sha256Checksum::PATTERN],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
