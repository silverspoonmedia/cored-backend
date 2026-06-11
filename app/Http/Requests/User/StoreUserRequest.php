<?php

namespace App\Http\Requests\User;

use App\Services\Rbac\RoleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        /** @var RoleService $roleService */
        $roleService = app(RoleService::class);
        $assignableNames = $roleService->getAssignableRoles($this->user())->pluck('name')->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'string', 'same:password'],
            'role' => ['required', 'string', Rule::in($assignableNames)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => __('forms/users.role_not_assignable'),
        ];
    }
}
