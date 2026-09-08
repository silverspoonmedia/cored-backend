<?php

namespace App\Domain\Archivd\Http\Requests;

use App\Domain\Archivd\Http\Requests\Concerns\ArchivdRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceMemberRoleRequest extends FormRequest
{
    use ArchivdRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->workspaceRoleRules();
    }
}
