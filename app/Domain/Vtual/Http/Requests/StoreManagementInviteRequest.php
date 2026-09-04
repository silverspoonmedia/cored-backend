<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\ManagementMemberRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagementInviteRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'role' => ['required', 'string', Rule::in(array_map(
                fn (ManagementMemberRole $r): string => $r->value,
                ManagementMemberRole::assignable(),
            ))],
        ];
    }
}
