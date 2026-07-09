<?php

namespace App\Domain\Identity\Http\Requests\Lookup;

use App\Domain\Navigation\Support\MenuLayout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class LookupMenusRequest extends LookupRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'layout' => ['sometimes', 'string', Rule::in(MenuLayout::all())],
            'exclude' => ['sometimes', 'nullable', 'uuid'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('layout')) {
            $this->merge(['layout' => MenuLayout::default()]);
        }
    }

    public function layout(): string
    {
        return (string) $this->validated('layout', MenuLayout::default());
    }

    public function excludeId(): ?string
    {
        $exclude = $this->validated('exclude');

        return is_string($exclude) && $exclude !== '' ? $exclude : null;
    }
}
