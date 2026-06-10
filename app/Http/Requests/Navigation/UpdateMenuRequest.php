<?php

namespace App\Http\Requests\Navigation;

use App\Http\Requests\Navigation\Concerns\NormalizesMenuPlacementFields;
use App\Http\Requests\Navigation\Concerns\ValidatesMenuStructure;
use App\Models\Menu;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMenuRequest extends FormRequest
{
    use NormalizesMenuPlacementFields;
    use ValidatesMenuStructure;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePlacementFields();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $routeMenu = $this->route('menu');
        $menuId = $routeMenu instanceof Menu ? $routeMenu->getKey() : (string) $routeMenu;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'size:1', Rule::in(['h', 'p', 'c'])],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'route' => ['sometimes', 'nullable', 'string', 'max:255', $this->namedRouteRule()],
            'parent' => ['sometimes', 'nullable', 'string', Rule::exists('menus', 'id')->whereNull('deleted_at'), Rule::notIn([$menuId])],
            'authenticate' => ['sometimes', 'boolean'],
            'guest_only' => ['sometimes', 'boolean'],
            'position' => ['nullable', 'string', Rule::in(['before', 'after'])],
            'reference_id' => [
                Rule::excludeIf(fn () => ! in_array($this->input('position'), ['before', 'after'], true)),
                'required',
                'string',
                Rule::exists('menus', 'id')->whereNull('deleted_at'),
            ],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string'],
            'user_includes' => ['sometimes', 'array'],
            'user_includes.*' => ['string', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'user_excludes' => ['sometimes', 'array'],
            'user_excludes.*' => ['string', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $menu = $this->route('menu');

            if (! $menu instanceof Menu) {
                return;
            }

            $guestOnly = $this->has('guest_only') ? $this->boolean('guest_only') : (bool) $menu->is_guest_only;
            $authenticate = $this->has('authenticate') ? $this->boolean('authenticate') : (bool) $menu->is_authenticate;
            $hasRoles = $this->has('roles') ? ($this->input('roles', []) !== []) : $menu->roles()->exists();

            $this->assertFlagsCompatible($validator, $guestOnly, $authenticate, $hasRoles);

            $changingType = $this->has('type');
            $changingParent = $this->has('parent');

            // Only enforce structure when the hierarchy is actually being changed,
            // so pure renames/reorders of pre-existing data are not rejected.
            if (! $changingType && ! $changingParent) {
                return;
            }

            $type = $changingType ? $this->input('type') : $menu->type;
            $parentId = $changingParent ? $this->input('parent') : $menu->parent_id;

            if ($changingParent) {
                $this->assertParentIsNotDescendant($validator, $menu->getKey(), $parentId);
            }

            if (is_string($type)) {
                $this->assertMenuStructure($validator, $type, $parentId);
            }
        });
    }

    /**
     * The `route` column stores a registered Laravel route name, not a URL.
     */
    private function namedRouteRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value) || ! Route::has($value)) {
                $fail('The :attribute must be a registered route name.');
            }
        };
    }
}
