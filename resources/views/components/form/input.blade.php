@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'icon' => null,
    'hint' => null,
])
@php($id = $attributes->get('id', $name))
@php($hasError = $errors->has($name))

<div class="mb-3">
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}@if ($attributes->has('required'))<span class="text-danger">*</span>@endif
        </label>
    @endif

    @if ($icon)
        <div class="input-group @if ($hasError) has-validation @endif">
            <input
                type="{{ $type }}"
                name="{{ $name }}"
                id="{{ $id }}"
                @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
                {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}
            />
            <span class="input-group-text"><i class="bi {{ $icon }}"></i></span>
            @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @else
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $id }}"
            @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}
        />
        @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @endif

    @if ($hint)
        <small class="form-text text-muted">{{ $hint }}</small>
    @endif
</div>
