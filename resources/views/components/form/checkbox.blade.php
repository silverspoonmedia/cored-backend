@props([
    'name',
    'label',
    'value' => 1,
    'checked' => false,
    'hint' => null,
])
{{-- Hidden field guarantees a value posts even when unchecked, so boolean()
     rules resolve to false instead of "missing". --}}
<div class="mb-3">
    <div class="form-check">
        <input type="hidden" name="{{ $name }}" value="0" />
        <input
            type="checkbox"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ $value }}"
            @checked(old($name, $checked))
            {{ $attributes->merge(['class' => 'form-check-input']) }}
        />
        <label class="form-check-label" for="{{ $name }}">{{ $label }}</label>
    </div>
    @if ($hint)
        <small class="form-text text-muted d-block">{{ $hint }}</small>
    @endif
    @error($name)<div class="text-danger small">{{ $message }}</div>@enderror
</div>
