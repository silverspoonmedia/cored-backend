<?php

namespace App\Http\Requests\Navigation;

use App\Http\Requests\Navigation\Concerns\NormalizesMenuPlacementFields;
use App\Http\Requests\Navigation\Concerns\ValidatesMenuStructure;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMenuRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'size:1', Rule::in(['h', 'p', 'c'])],
            'icon' => ['nullable', 'string', 'max:255'],
            'route' => ['nullable', 'string', 'max:255', $this->namedRouteRule()],
            'parent' => ['nullable', 'string', Rule::exists('menus', 'id')->whereNull('deleted_at')],
            'authenticate' => ['sometimes', 'boolean'],
            'guest_only' => ['sometimes', 'boolean'],
            'position' => ['nullable', 'string', Rule::in(['before', 'after', 'append'])],
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
            $type = $this->input('type');

            if (is_string($type)) {
                $this->assertMenuStructure($validator, $type, $this->input('parent'));
            }

            $this->assertFlagsCompatible(
                $validator,
                $this->boolean('guest_only'),
                $this->boolean('authenticate'),
                $this->input('roles', []) !== [],
            );
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
