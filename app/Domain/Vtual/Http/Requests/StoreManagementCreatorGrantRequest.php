<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\VtualGrantCapability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Delegate one capability over one creator to one org member
 * (docs/domains/vtual/reference-capability-grants.md).
 *
 * `member_id` is a `vtual_management_members` row, not a user: the delegation
 * target must already belong to the org, which is what keeps this from
 * becoming a second membership model.
 */
class StoreManagementCreatorGrantRequest extends FormRequest
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
            'member_id' => ['required', 'uuid', 'exists:vtual_management_members,id'],
            'creator_id' => ['required', 'uuid', 'exists:vtual_creators,id'],
            'capability' => ['required', 'string', Rule::in(VtualGrantCapability::values())],
        ];
    }
}
